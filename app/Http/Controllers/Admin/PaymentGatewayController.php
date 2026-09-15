<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePaymentGatewayRequest;
use App\Models\Currency;
use App\Models\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class PaymentGatewayController extends Controller
{
    public function index()
    {
        $gateways = PaymentGateway::query()->with('currencies')->withCount('transactions')->orderBy('ordering')->orderBy('id')->get();
        $currencies = Currency::query()->where('active', true)->orderBy('ordering')->orderBy('code')->get();
        return view('admin.settings.payment.index', compact('gateways', 'currencies'));
    }

    public function store(SavePaymentGatewayRequest $request): RedirectResponse
    {
        $this->ensureActiveCurrencies($request->validated('currency_ids'));
        DB::transaction(function () use ($request): void {
            $gateway = PaymentGateway::create($this->payload($request));
            $gateway->currencies()->sync($request->validated('currency_ids'));
        });
        return back()->with('ok', 'Payment gateway created.');
    }

    public function update(SavePaymentGatewayRequest $request, PaymentGateway $gateway): RedirectResponse
    {
        $this->ensureActiveCurrencies($request->validated('currency_ids'));
        $oldLogo = $gateway->logo_path;
        DB::transaction(function () use ($request, $gateway): void {
            $gateway->update($this->payload($request, $gateway));
            $gateway->currencies()->sync($request->validated('currency_ids'));
        });
        if ($oldLogo && $oldLogo !== $gateway->logo_path && str_starts_with($oldLogo, 'payments/')) {
            Storage::disk('public')->delete($oldLogo);
        }
        return back()->with('ok', 'Payment gateway updated.');
    }

    public function destroy(PaymentGateway $gateway): RedirectResponse
    {
        if ($gateway->transactions()->exists()) {
            return back()->withErrors(['gateway' => 'This gateway has payment history. Disable it instead of deleting it.']);
        }
        $logo = $gateway->logo_path;
        $gateway->delete();
        if ($logo && str_starts_with($logo, 'payments/')) {
            Storage::disk('public')->delete($logo);
        }
        return back()->with('ok', 'Payment gateway deleted.');
    }

    private function payload(SavePaymentGatewayRequest $request, ?PaymentGateway $gateway = null): array
    {
        $data = $request->safe()->except(['currency_ids', 'logo', 'client_id', 'api_key', 'api_secret', 'webhook_secret', 'payment_details']);
        $data['active'] = $request->boolean('active');
        $data['sandbox'] = $request->boolean('sandbox');
        $data['ordering'] = (int) ($data['ordering'] ?? 0);
        $data['config'] = ['payment_details' => trim((string) $request->input('payment_details'))];
        $data['logo_path'] = $gateway?->logo_path;
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('payments', 'public');
        }

        if ($data['driver'] === 'manual') {
            $data['credentials'] = null;
        } else {
            $keys = ['client_id', 'api_key', 'api_secret', 'webhook_secret'];
            $hasReplacement = collect($keys)->contains(fn (string $key) => $request->filled($key));
            if (!$gateway || $gateway->driver !== $data['driver'] || $hasReplacement) {
                $credentials = $gateway && $gateway->driver === $data['driver'] ? ($gateway->credentials ?? []) : [];
                foreach ($keys as $key) {
                    if ($request->filled($key)) {
                        $credentials[$key] = (string) $request->input($key);
                    }
                }
                $data['credentials'] = $credentials ?: null;
            }
        }
        return $data;
    }

    private function ensureActiveCurrencies(array $ids): void
    {
        if (Currency::query()->where('active', true)->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['currency_ids' => 'Select active currencies only.']);
        }
    }
}
