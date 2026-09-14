<?php

namespace Tests\Unit;

use App\Models\SmmService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SmmServiceNameCastTest extends TestCase
{
    public function test_translated_name_is_array_accessible_and_string_safe(): void
    {
        $service = new SmmService();
        $service->setRawAttributes([
            'name' => json_encode([
                'en' => 'Example SMM Service',
                'fallback' => 'Example SMM Service',
            ]),
        ]);

        $name = $service->name;

        $this->assertInstanceOf(Collection::class, $name);
        $this->assertSame('Example SMM Service', $name['en']);
        $this->assertStringContainsString('Example SMM Service', (string) $name);
    }
}
