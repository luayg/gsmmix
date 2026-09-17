<?php

namespace Tests\Unit;

use App\Models\ServerService;
use App\Support\ProductService;
use Tests\TestCase;

class ProductServiceDisplayTextTest extends TestCase
{
    public function test_service_names_are_rendered_as_translated_text_instead_of_json(): void
    {
        app()->setLocale('en');
        $service = new ServerService();
        $service->name = ['en' => 'AMT Android Multi Tool', 'fallback' => 'Fallback name'];

        $this->assertSame('AMT Android Multi Tool', ProductService::displayName($service));
        $this->assertSame('V-Ray EDU Account', ProductService::displayText('{"en":"V-Ray EDU Account","fallback":"Fallback"}'));
    }
}
