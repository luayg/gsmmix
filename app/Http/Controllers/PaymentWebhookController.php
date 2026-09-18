<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\BinancePayGateway;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\PaymentSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PaymentWebhookController extends Controller
{
    public function __invoke(string $slug, Request $request, PayPalGateway $paypal, BinancePayGateway $binance, PaymentSettlement $settlement)
    {
        $gateway = PaymentGateway::query()->where('slug', $slug)->where('is_system', true)->where('active', true)->firstOrFail();
        $payload = $request->json()->all();
        $eventId = (string) ($payload['id'] ?? data_get($payload, 'bizId') ?? hash('sha256', $request->getContent()));

        // Never persist an event identifier before authenticating the request. An
        // attacker could otherwise submit a guessed provider event ID first and
        // make the later, legitimate webhook look like an already handled replay.
        $verified = match ($gateway->driver) {
            'paypal' => $paypal->verifyWebhook($gateway, $request),
            'binance_pay' => $binance->verifyWebhook($gateway, $request),
            default => false,
        };
        abort_unless($verified, 401, 'Invalid payment webhook signature.');

        $event = PaymentWebhookEvent::firstOrCreate(
            ['payment_gateway_id'=>$gateway->id, 'event_id'=>$eventId],
            ['payload_hash'=>hash('sha256', $request->getContent()), 'status'=>'received']
        );
        if (in_array($event->status, ['processed', 'ignored'], true)) return response()->json(['ok'=>true]);
        if (!$event->wasRecentlyCreated) $event->update(['status'=>'received','error'=>null,'processed_at'=>null]);

        try {
            [$reference, $externalId, $paid] = $this->details($gateway->driver, $payload);
            if (!$paid || !$reference) {
                $event->update(['status'=>'ignored','processed_at'=>now()]);
                return response()->json(['ok'=>true]);
            }
            $payment = PaymentTransaction::query()->where('uuid', $reference)->where('payment_gateway_id', $gateway->id)->firstOrFail();
            $settlement->paid($payment, $externalId ?: $eventId, ['event_id'=>$eventId]);
            $event->update(['status'=>'processed','processed_at'=>now()]);
            return response()->json(['ok'=>true]);
        } catch (Throwable $e) {
            $event->update(['status'=>'failed','error'=>str($e->getMessage())->limit(2000),'processed_at'=>now()]);
            Log::error('Payment webhook failed', ['gateway'=>$gateway->slug,'event_id'=>$eventId,'exception'=>$e]);
            throw $e;
        }
    }

    private function details(string $driver, array $payload): array
    {
        if ($driver === 'paypal') {
            return [(string) data_get($payload,'resource.custom_id'), (string) data_get($payload,'resource.id'), data_get($payload,'event_type') === 'PAYMENT.CAPTURE.COMPLETED' && data_get($payload,'resource.status') === 'COMPLETED'];
        }
        return [(string) (data_get($payload,'data.passThroughInfo') ?? data_get($payload,'data.merchantTradeNo') ?? data_get($payload,'bizId')), (string) data_get($payload,'data.transactionId'), data_get($payload,'bizStatus') === 'PAY_SUCCESS'];
    }
}
