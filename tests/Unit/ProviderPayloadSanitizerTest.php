<?php

namespace Tests\Unit;

use App\Services\Orders\ProviderPayloadSanitizer;
use PHPUnit\Framework\TestCase;

class ProviderPayloadSanitizerTest extends TestCase
{
    public function test_request_credentials_are_redacted_recursively_and_in_urls(): void
    {
        $sanitizer = new ProviderPayloadSanitizer();

        $clean = $sanitizer->sanitizeRequest([
            'url' => 'https://provider.test/api?api_key=secret-value&foo=bar',
            'payload' => [
                'username' => 'account-name',
                'apiaccesskey' => 'secret-access-key',
                'nested' => ['Key' => 'secret-generic-key'],
            ],
        ]);

        $this->assertSame('https://provider.test/api?api_key=[REDACTED]&foo=bar', $clean['url']);
        $this->assertSame('account-name', $clean['payload']['username']);
        $this->assertSame('[REDACTED]', $clean['payload']['apiaccesskey']);
        $this->assertSame('[REDACTED]', $clean['payload']['nested']['Key']);
    }

    public function test_response_keeps_customer_result_key_but_redacts_api_credentials(): void
    {
        $sanitizer = new ProviderPayloadSanitizer();

        $clean = $sanitizer->sanitizeResponse([
            'key' => 'customer-unlock-result',
            'api_key' => 'provider-secret',
            'url' => 'https://provider.test/result?access_token=provider-token',
        ]);

        $this->assertSame('customer-unlock-result', $clean['key']);
        $this->assertSame('[REDACTED]', $clean['api_key']);
        $this->assertSame('https://provider.test/result?access_token=[REDACTED]', $clean['url']);
    }
}
