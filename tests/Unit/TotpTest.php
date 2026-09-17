<?php

namespace Tests\Unit;

use App\Services\Auth\Totp;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    public function test_it_matches_the_rfc_6238_sha1_vector_and_allows_clock_skew(): void
    {
        $totp=new Totp;
        $secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertTrue($totp->verify($secret,'287082',59));
        $this->assertTrue($totp->verify($secret,'287082',89));
        $this->assertFalse($totp->verify($secret,'287083',59));
    }

    public function test_generated_secret_and_uri_are_authenticator_compatible(): void
    {
        $totp=new Totp; $secret=$totp->secret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/',$secret);
        $this->assertStringStartsWith('otpauth://totp/',$totp->uri($secret,'user@example.test','GSM MIX'));
        $this->assertStringContainsString('secret='.$secret,$totp->uri($secret,'user@example.test','GSM MIX'));
    }
}
