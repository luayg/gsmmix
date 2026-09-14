<?php

namespace App\Observers;

use App\Services\Orders\ProviderPayloadSanitizer;
use Illuminate\Database\Eloquent\Model;

final class OrderProviderMetadataSanitizerObserver
{
    public function __construct(private readonly ProviderPayloadSanitizer $sanitizer) {}

    public function saving(Model $order): void
    {
        $request = $order->request ?? [];
        if (is_string($request)) {
            $decoded = json_decode($request, true);
            $request = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($request)) {
            return;
        }

        if (array_key_exists('request', $request)) {
            $request['request'] = $this->sanitizer->sanitizeRequest($request['request']);
        }

        if (array_key_exists('response_raw', $request)) {
            $request['response_raw'] = $this->sanitizer->sanitizeResponse($request['response_raw']);
        }

        $order->request = $request;
    }
}
