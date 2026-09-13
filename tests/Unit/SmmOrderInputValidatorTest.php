<?php

namespace Tests\Unit;

use App\Models\SmmService;
use App\Services\Orders\SmmOrderInputValidator;
use Tests\TestCase;

class SmmOrderInputValidatorTest extends TestCase
{
    private function service(): SmmService
    {
        $service = new SmmService();
        $service->params = [
            'custom_fields' => [
                [
                    'active' => 1,
                    'required' => 1,
                    'name' => 'Link',
                    'input' => 'link',
                    'type' => 'text',
                    'minimum' => 0,
                    'maximum' => 0,
                ],
                [
                    'active' => 1,
                    'required' => 1,
                    'name' => 'Quantity',
                    'input' => 'quantity',
                    'type' => 'number',
                    'minimum' => 100,
                    'maximum' => 1000,
                ],
            ],
        ];
        return $service;
    }

    public function test_numeric_minimum_and_maximum_are_values_not_character_lengths(): void
    {
        $validator = app(SmmOrderInputValidator::class);

        $valid = $validator->validate($this->service(), ['link' => 'https://example.test/x', 'quantity' => '100']);
        $this->assertSame([], $valid);

        $tooSmall = $validator->validate($this->service(), ['link' => 'https://example.test/x', 'quantity' => '99']);
        $this->assertSame('Quantity must be at least 100.', $tooSmall['required.quantity'] ?? null);

        $tooLarge = $validator->validate($this->service(), ['link' => 'https://example.test/x', 'quantity' => '1001']);
        $this->assertSame('Quantity must be at most 1000.', $tooLarge['required.quantity'] ?? null);
    }
}
