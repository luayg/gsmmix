<?php

namespace Tests\Unit;

use App\Services\Content\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    public function test_it_preserves_formatting_and_removes_executable_markup(): void
    {
        $clean = (new HtmlSanitizer())->clean('<p onclick="alert(1)">Safe <strong>text</strong><script>alert(2)</script><a href="javascript:alert(3)" style="position:fixed">link</a></p>');

        $this->assertStringContainsString('<p>Safe <strong>text</strong>', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('style=', $clean);
    }

    public function test_it_allows_only_safe_image_data_urls(): void
    {
        $clean = (new HtmlSanitizer())->clean('<img src="data:text/html;base64,PHNjcmlwdD4=" onerror="alert(1)"><img src="data:image/png;base64,AAAA">');

        $this->assertStringNotContainsString('data:text/html', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('data:image/png;base64,AAAA', $clean);
    }
}
