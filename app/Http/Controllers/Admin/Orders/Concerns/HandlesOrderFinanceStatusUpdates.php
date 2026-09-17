<?php

namespace App\Http\Controllers\Admin\Orders\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait HandlesOrderFinanceStatusUpdates
{
    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'status'              => ['required', 'in:waiting,inprogress,success,rejected,cancelled'],
            'comments'            => ['nullable', 'string'],
            'response'            => ['nullable'],
            'provider_reply_html' => ['nullable', 'string'],
        ]);

        try {
            DB::transaction(function () use ($data, $id): void {
                $row = ($this->orderModel)::query()->lockForUpdate()->findOrFail($id);
                $oldStatus = strtolower(trim((string)($row->status ?? '')));
                $newStatus = strtolower(trim((string)$data['status']));
                $remoteId = trim((string)($row->remote_id ?? ''));

                // An order cannot be genuinely in progress at a provider until it has
                // a provider-side reference. Keeping such an order as waiting also
                // lets the atomic dispatch worker pick it up safely.
                if ($newStatus === 'inprogress' && $remoteId === '') {
                    throw ValidationException::withMessages([
                        'status' => 'In progress requires a provider remote ID. Keep the order Waiting until it is sent.',
                    ]);
                }

                // A provider-owned order must never be moved back into the unsent
                // waiting bucket. Treat the manual request as in-progress instead.
                if ($newStatus === 'waiting' && $remoteId !== '') {
                    $newStatus = 'inprogress';
                }

                // A terminal failure must be refunded exactly once. Re-activating any
                // refunded order (waiting/inprogress/success) must re-charge first.
                if (in_array($newStatus, ['rejected', 'cancelled'], true)) {
                    $this->finance()->refundOrderIfNeeded($row, 'manual_' . $newStatus);
                } else {
                    $this->finance()->rechargeOrderIfNeeded($row, 'manual_' . $newStatus);
                }

                // The finance service writes request metadata through a separately
                // locked model instance. Refresh before saving the status/UI fields.
                $row->refresh();
                $row->status = $newStatus;
                $row->comments = (string)($data['comments'] ?? '');

                $currentResponse = $row->response;
                if (is_string($currentResponse)) {
                    $decoded = json_decode($currentResponse, true);
                    $currentResponse = is_array($decoded) ? $decoded : ['raw' => $row->response];
                } elseif (!is_array($currentResponse) && $currentResponse !== null) {
                    $currentResponse = ['raw' => $currentResponse];
                } elseif ($currentResponse === null) {
                    $currentResponse = [];
                }

                if (array_key_exists('response', $data)) {
                    if (is_string($data['response'])) {
                        $decoded = json_decode($data['response'], true);
                        if (is_array($decoded)) {
                            $currentResponse = array_merge($currentResponse, $decoded);
                        } else {
                            $currentResponse['raw'] = $data['response'];
                        }
                    } elseif (is_array($data['response'])) {
                        $currentResponse = array_merge($currentResponse, $data['response']);
                    }
                }

                if (array_key_exists('provider_reply_html', $data)) {
                    $currentResponse['provider_reply_html'] = (string) ($data['provider_reply_html'] ?? '');
                    $currentResponse['provider_reply_updated_at'] = now()->toDateTimeString();
                }

                $row->response = $currentResponse;

                if (in_array($newStatus, ['success', 'rejected', 'cancelled'], true)) {
                    $row->processing = false;
                    // Editing comments/result on an already-final order must not rewrite
                    // the original reply time on every save.
                    if (!$row->replied_at || $oldStatus !== $newStatus) {
                        $row->replied_at = now();
                    }
                } else {
                    // Active status transitions must leave deterministic worker state.
                    $row->processing = $newStatus === 'inprogress';
                    $row->replied_at = null;
                }

                $row->save();
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'INSUFFICIENT_BALANCE_RECHARGE') {
                return redirect()->back()
                    ->withErrors(['status' => 'User balance is not enough to reactivate this refunded order.'])
                    ->withInput();
            }

            throw $exception;
        }

        return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order updated.');
    }
}
