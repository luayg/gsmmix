<?php

namespace App\Http\Controllers\Admin\Orders\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                $newStatus = strtolower(trim((string)$data['status']));

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
                $row->status = $data['status'];
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

                if (!empty($data['provider_reply_html'])) {
                    $currentResponse['provider_reply_html'] = $data['provider_reply_html'];
                    $currentResponse['provider_reply_updated_at'] = now()->toDateTimeString();
                }

                $row->response = $currentResponse;
                if (in_array($newStatus, ['success', 'rejected', 'cancelled'], true)) {
                    $row->processing = false;
                    $row->replied_at = now();
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
