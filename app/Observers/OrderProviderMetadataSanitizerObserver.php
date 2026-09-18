<?php

namespace App\Observers;

use App\Services\Content\HtmlSanitizer;
use App\Services\Orders\ProviderPayloadSanitizer;
use Illuminate\Database\Eloquent\Model;

final class OrderProviderMetadataSanitizerObserver
{
    public function __construct(
        private readonly ProviderPayloadSanitizer $sanitizer,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {}

    public function saving(Model $order): void
    {
        $request = $order->request ?? [];
        if (is_string($request)) {
            $decoded = json_decode($request, true);
            $request = is_array($decoded) ? $decoded : [];
        }

        if (is_array($request)) {
            if (array_key_exists('request', $request)) {
                $request['request'] = $this->sanitizer->sanitizeRequest($request['request']);
            }

            if (array_key_exists('response_raw', $request)) {
                $request['response_raw'] = $this->sanitizer->sanitizeResponse($request['response_raw']);
            }

            $order->request = $request;
        }

        $response = $order->response ?? [];
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            $response = is_array($decoded) ? $decoded : null;
        }
        if (is_array($response) && array_key_exists('provider_reply_html', $response)) {
            $response['provider_reply_html'] = $this->htmlSanitizer->clean(
                (string) ($response['provider_reply_html'] ?? '')
            );
            $order->response = $response;
        }
    }
}
