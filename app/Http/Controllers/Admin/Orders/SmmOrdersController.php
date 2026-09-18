<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Admin\Orders\Concerns\HandlesOrderFinanceStatusUpdates;
use App\Models\ApiProvider;
use App\Models\SmmOrder;
use App\Models\SmmService;
use App\Models\User;
use App\Services\Orders\SmmOrderInputValidator;
use App\Services\Orders\SmmPricingException;
use App\Services\Orders\SmmPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SmmOrdersController extends BaseOrdersController
{
    use HandlesOrderFinanceStatusUpdates;

    protected string $orderModel   = SmmOrder::class;
    protected string $serviceModel = SmmService::class;

    protected string $kind        = 'smm';
    protected string $title       = 'SMM Orders';
    protected string $routePrefix = 'admin.orders.smm';

    protected function deviceLabel(): string
    {
        return 'Link / Username / Target';
    }

    protected function supportsQuantity(): bool
    {
        return true;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'     => ['required', 'integer'],
            'service_id'  => ['required', 'integer'],
            'device'      => ['nullable', 'string', 'max:2000'],
            'quantity'    => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'required'    => ['nullable', 'array'],
            'comments'    => ['nullable', 'string'],
            'request_uid' => ['required', 'string', 'max:100'],
        ]);

        $requestUid = trim((string)($data['request_uid'] ?? ''));
        $sessionId = (string)$request->session()->getId();
        $submitLockKey = 'order_submit_lock:' . sha1('smm|' . $sessionId . '|' . $requestUid);

        if (!Cache::add($submitLockKey, now()->toDateTimeString(), now()->addMinutes(10))) {
            return $this->duplicateSubmitResponse($request);
        }

        try {
            $user = User::find((int)$data['user_id']);
            if (!$user) {
                Cache::forget($submitLockKey);
                return $this->failValidation($request, ['user_id' => 'User not found.']);
            }

            $service = SmmService::query()
                ->where('id', (int)$data['service_id'])
                ->where('active', 1)
                ->first();
            if (!$service) {
                Cache::forget($submitLockKey);
                return $this->failValidation($request, ['service_id' => 'Service is not active or not found.']);
            }

            $fields = isset($data['required']) && is_array($data['required']) ? $data['required'] : [];
            $device = trim((string)($data['device'] ?? ''));

            $inputErrors = app(SmmOrderInputValidator::class)->validate($service, $fields, $device);
            if (!empty($inputErrors)) {
                Cache::forget($submitLockKey);
                return $this->failValidation($request, $inputErrors);
            }

            try {
                $quote = app(SmmPricingService::class)->quote(
                    $service,
                    $user,
                    $fields,
                    (int)($data['quantity'] ?? 1)
                );
            } catch (SmmPricingException $e) {
                Cache::forget($submitLockKey);
                return $this->failValidation($request, $e->errors());
            }

            if ($device !== '' && !$this->hasTargetField($fields)) {
                $fields['custom'] = $device;
            }

            $supplierId = (int)($service->supplier_id ?? 0);
            $provider = $supplierId > 0 ? ApiProvider::find($supplierId) : null;
            // Provider availability is intentionally not part of this decision.
            // API orders remain queued while a provider is offline/misconfigured
            // and the scheduler submits them automatically after it is repaired.
            $isApi = (int)($service->source ?? 0) === 2
                || $supplierId > 0
                || trim((string)$service->remote_id) !== '';

            $chargedAmount = (string)$quote['sell_total'];
            $displayTarget = $this->firstTarget($fields, $device);

            $order = DB::transaction(function () use (
                $request, $data, $requestUid, $user, $service, $provider, $isApi,
                $fields, $quote, $chargedAmount, $displayTarget
            ): SmmOrder {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $balance = $this->money4($lockedUser->balance ?? 0);

                if (bccomp($chargedAmount, '0.0000', 4) === 1 && bccomp($balance, $chargedAmount, 4) === -1) {
                    throw new \RuntimeException('INSUFFICIENT_BALANCE');
                }

                if (bccomp($chargedAmount, '0.0000', 4) === 1) {
                    $lockedUser->balance = bcsub($balance, $chargedAmount, 4);
                    $lockedUser->save();
                }

                $order = new SmmOrder();
                $order->comments = (string)($data['comments'] ?? '');
                $order->user_id = $lockedUser->id;
                $order->email = $lockedUser->email ?: null;
                $order->service_id = (int)$service->id;
                $order->supplier_id = $provider?->id;
                $order->device = $displayTarget;
                $order->quantity = (int)$quote['effective_quantity'];
                $order->status = 'waiting';
                $order->processing = false;
                $order->api_order = $isApi ? 1 : 0;
                $order->price = (string)$quote['sell_total'];
                $order->order_price = (string)$quote['provider_total'];
                $order->profit = (string)$quote['profit_total'];
                $order->ip = $request->ip();

                $order->params = [
                    'kind' => 'smm',
                    'quantity' => (int)$quote['effective_quantity'],
                    'fields' => $fields,
                    'billing' => [
                        'version' => 1,
                        'smm_type' => $quote['smm_type'],
                        'mode' => $quote['billing_mode'],
                        'price_unit' => $quote['price_unit'],
                        'sell_rate' => $quote['sell_rate'],
                        'provider_rate' => $quote['provider_rate'],
                        'billable_units' => $quote['billable_units'],
                        'minimum' => $quote['minimum'],
                        'maximum' => $quote['maximum'],
                        'meta' => $quote['meta'],
                    ],
                ];

                $order->request = [
                    'charged_amount' => (float)$chargedAmount,
                    'charged_at' => now()->toDateTimeString(),
                    'request_uid' => $requestUid,
                    'financial_state' => 'charged',
                    'billing_version' => 1,
                ];

                $order->save();
                return $order;
            });

        } catch (\RuntimeException $e) {
            Cache::forget($submitLockKey);

            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return $this->failValidation($request, ['user_id' => 'No enough balance for this order.']);
            }

            throw $e;
        } catch (\Throwable $e) {
            Cache::forget($submitLockKey);
            throw $e;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Order created.',
                'redirect_url' => route("{$this->routePrefix}.index"),
            ]);
        }

        return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order created.');
    }

    private function failValidation(Request $request, array $errors)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation error',
                'errors' => $errors,
            ], 422);
        }

        return redirect()->back()->withErrors($errors)->withInput();
    }

    private function duplicateSubmitResponse(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Order already submitted.',
                'redirect_url' => route("{$this->routePrefix}.index"),
            ]);
        }

        return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order already submitted.');
    }

    private function hasTargetField(array $fields): bool
    {
        foreach (['link', 'target', 'url', 'page', 'channel', 'post', 'username', 'usernames', 'custom'] as $key) {
            $value = $fields[$key] ?? null;
            if (is_array($value)) {
                if (!empty(array_filter(array_map('trim', $value)))) {
                    return true;
                }
            } elseif (trim((string)$value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function firstTarget(array $fields, string $fallback): string
    {
        foreach (['link', 'target', 'url', 'page', 'channel', 'post', 'username', 'custom'] as $key) {
            $value = trim((string)($fields[$key] ?? ''));
            if ($value !== '') {
                return mb_substr(preg_replace('/\s+/', ' ', $value) ?? $value, 0, 255);
            }
        }

        return mb_substr($fallback, 0, 255);
    }

    private function money4($value): string
    {
        return number_format(is_numeric($value) ? (float)$value : 0.0, 4, '.', '');
    }
}
