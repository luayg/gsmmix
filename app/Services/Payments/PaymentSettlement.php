<?php

namespace App\Services\Payments;

use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\PaymentTransaction;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PaymentSettlement
{
    public function paid(PaymentTransaction $payment, string $externalId, array $providerMetadata = []): PaymentTransaction
    {
        return DB::transaction(function () use ($payment, $externalId, $providerMetadata): PaymentTransaction {
            $locked = PaymentTransaction::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === 'paid') return $locked;
            if (!in_array($locked->status, ['pending', 'review'], true)) throw new RuntimeException('A terminal payment cannot be settled.');
            if (!$locked->user_id) throw new RuntimeException('Payment has no customer account.');

            $user = User::query()->lockForUpdate()->findOrFail($locked->user_id);
            $before = bcadd((string) ($user->balance ?? 0), '0', 4);
            $credit = bcadd((string) $locked->amount_base, '0', 4);
            $after = bcadd($before, $credit, 4);
            $user->forceFill(['balance' => $after])->save();

            $account = FinanceAccount::firstOrCreate(['user_id' => $user->id], ['locked_amount'=>0,'total_receipts'=>0,'paid_credits'=>0,'overdraft_limit'=>0]);
            $account = FinanceAccount::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $account->total_receipts = bcadd((string) $account->total_receipts, $credit, 4);
            $account->paid_credits = bcadd((string) $account->paid_credits, $credit, 4);
            $account->save();

            $metadata = array_merge($locked->metadata ?? [], ['provider' => $providerMetadata]);
            if (isset($providerMetadata['manual_review'])) {
                $metadata['manual_review'] = $providerMetadata['manual_review'];
            }

            $locked->forceFill([
                'status' => 'paid', 'external_id' => $externalId, 'paid_at' => now(),
                'metadata' => $metadata,
                'approved_by' => data_get($providerMetadata, 'manual_review.reviewed_by', $locked->approved_by),
            ])->save();

            $financeTransaction = FinanceTransaction::create([
                'user_id'=>$user->id, 'kind'=>'payment', 'direction'=>'income', 'paid'=>true,
                'amount'=>$credit, 'currency_code'=>$locked->currency_code,
                'original_amount'=>$locked->payable_currency, 'exchange_rate'=>$locked->exchange_rate,
                'reference'=>'payment:'.$locked->uuid,
                'note'=>isset($providerMetadata['manual_review'])
                    ? 'Manual payment approved via '.$locked->gateway?->name
                    : 'Automatic payment via '.$locked->gateway?->name,
                'balance_before'=>$before, 'balance_after'=>$after,
                'source_type'=>PaymentTransaction::class, 'source_id'=>$locked->id,
            ]);

            $invoice = Invoice::firstOrCreate(
                ['source_type' => PaymentTransaction::class, 'source_id' => $locked->id],
                [
                    'number' => 'PAY-'.now()->format('Ymd').'-'.str_pad((string)$locked->id, 6, '0', STR_PAD_LEFT),
                    'user_id' => $user->id, 'status' => 'paid', 'currency_code' => $locked->currency_code,
                    'exchange_rate' => $locked->exchange_rate, 'customer_snapshot' => ['name'=>$user->name,'email'=>$user->email,'username'=>$user->username],
                    'company_snapshot' => ['name'=>Setting::where('setting_key','general.site_name')->value('value') ?: config('app.name'),'email'=>Setting::where('setting_key','general.email')->value('value')],
                    'subtotal' => $locked->amount_base, 'fee_total' => $locked->fee_base, 'total' => $locked->payable_base,
                    'paid_total' => $locked->payable_base, 'issued_at' => now()->toDateString(), 'due_at' => now()->toDateString(), 'paid_at' => now(),
                    'notes' => 'Payment received via '.($locked->gateway?->name ?: 'payment gateway'),
                ]
            );
            if ($invoice->wasRecentlyCreated) {
                $invoice->items()->create(['description'=>'Account balance payment','quantity'=>1,'unit_price'=>$locked->amount_base,'discount'=>0,'tax_rate'=>0,'line_total'=>$locked->amount_base,'ordering'=>0]);
                $invoice->payments()->create(['payment_transaction_id'=>$locked->id,'finance_transaction_id'=>$financeTransaction->id,'amount'=>$locked->payable_base,'currency_code'=>$locked->currency_code,'exchange_rate'=>$locked->exchange_rate,'reference'=>$externalId,'paid_at'=>now()]);
            }
            return $locked;
        });
    }
}
