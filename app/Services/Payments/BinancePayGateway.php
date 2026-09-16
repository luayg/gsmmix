<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class BinancePayGateway
{
    public function createOrder(PaymentGateway $gateway, string $reference, string $amount, string $currency, string $webhookUrl): array
    {
        $body = json_encode([
            'env'=>['terminalType'=>'WEB'], 'merchantTradeNo'=>str_replace('-', '', $reference),
            'orderAmount'=>$amount, 'currency'=>$currency, 'description'=>'Account balance payment',
            'goodsDetails'=>[['goodsType'=>'02','goodsCategory'=>'Z000','referenceGoodsId'=>$reference,'goodsName'=>'Account balance']],
            'passThroughInfo'=>$reference, 'webhookUrl'=>$webhookUrl,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->getTimestampMs();
        $nonce = bin2hex(random_bytes(16));
        $credentials = $gateway->credentials ?? [];
        $signature = strtoupper(hash_hmac('sha512', $timestamp."\n".$nonce."\n".$body."\n", $credentials['binance_secret_key'] ?? ''));
        $response = Http::withBody($body, 'application/json')->withHeaders([
            'BinancePay-Timestamp'=>$timestamp, 'BinancePay-Nonce'=>$nonce,
            'BinancePay-Certificate-SN'=>$credentials['binance_api_key'] ?? '', 'BinancePay-Signature'=>$signature,
        ])->timeout(20)->retry(2,300)->post('https://bpay.binanceapi.com/binancepay/openapi/v3/order')->throw()->json();
        if (($response['status'] ?? null) !== 'SUCCESS') throw new RuntimeException($response['errorMessage'] ?? 'Binance Pay order creation failed.');
        return ['external_id'=>(string) data_get($response,'data.prepayId'), 'checkout_url'=>(string) data_get($response,'data.checkoutUrl'), 'qr_content'=>data_get($response,'data.qrContent')];
    }

    public function verifyWebhook(PaymentGateway $gateway, Request $request): bool
    {
        $timestamp = (string) $request->header('Binancepay-Timestamp');
        $nonce = (string) $request->header('Binancepay-Nonce');
        $signature = base64_decode((string) $request->header('Binancepay-Signature'), true);
        $publicKey = data_get($gateway->credentials, 'binance_webhook_public_key');
        if (!$timestamp || !$nonce || $signature === false || !$publicKey || abs(now()->getTimestampMs() - (int) $timestamp) > 300000) return false;
        return openssl_verify($timestamp."\n".$nonce."\n".$request->getContent()."\n", $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }
}
