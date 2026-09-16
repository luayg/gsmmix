<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PayPalGateway
{
    public function createOrder(PaymentGateway $gateway, string $reference, string $amount, string $currency, string $returnUrl, string $cancelUrl): array
    {
        $response = $this->client($gateway)->withHeaders(['PayPal-Request-Id'=>$reference])->post('/v2/checkout/orders', [
            'intent'=>'CAPTURE',
            'purchase_units'=>[['reference_id'=>$reference,'custom_id'=>$reference,'amount'=>['currency_code'=>$currency,'value'=>$amount]]],
            'payment_source'=>['paypal'=>['experience_context'=>['user_action'=>'PAY_NOW','return_url'=>$returnUrl,'cancel_url'=>$cancelUrl]]],
        ])->throw()->json();
        $payerAction = collect($response['links'] ?? [])->firstWhere('rel','payer-action');
        if (!$payerAction) throw new RuntimeException('PayPal did not return a checkout URL.');
        return ['external_id'=>(string) $response['id'], 'checkout_url'=>(string) $payerAction['href']];
    }

    public function verifyWebhook(PaymentGateway $gateway, Request $request): bool
    {
        $response = $this->client($gateway)->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo'=>$request->header('PAYPAL-AUTH-ALGO'), 'cert_url'=>$request->header('PAYPAL-CERT-URL'),
            'transmission_id'=>$request->header('PAYPAL-TRANSMISSION-ID'), 'transmission_sig'=>$request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time'=>$request->header('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id'=>data_get($gateway->credentials, 'paypal_webhook_id'), 'webhook_event'=>$request->json()->all(),
        ])->throw()->json();
        return ($response['verification_status'] ?? null) === 'SUCCESS';
    }

    private function client(PaymentGateway $gateway): PendingRequest
    {
        $credentials = $gateway->credentials ?? [];
        $base = $gateway->sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $token = Http::asForm()->withBasicAuth($credentials['client_id'] ?? '', $credentials['client_secret'] ?? '')
            ->post($base.'/v1/oauth2/token', ['grant_type'=>'client_credentials'])->throw()->json('access_token');
        if (!$token) throw new RuntimeException('PayPal did not return an access token.');
        return Http::baseUrl($base)->acceptJson()->withToken($token)->timeout(20)->retry(2, 300);
    }
}
