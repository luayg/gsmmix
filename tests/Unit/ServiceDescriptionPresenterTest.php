<?php
namespace Tests\Unit;
use App\Support\ServiceDescriptionPresenter;
use PHPUnit\Framework\TestCase;
class ServiceDescriptionPresenterTest extends TestCase
{
 public function test_plain_concatenated_provider_description_becomes_vertical_rows():void{$html=ServiceDescriptionPresenter::format('Try our check. Sample:Model Description: IPHONE 15 IMEI Number: 123 Warranty Status: AppleCare+ Find My iPhone: ON iCloud Status: Clean');$this->assertStringContainsString('service-description-stack',$html);$this->assertStringContainsString('<span>Model Description</span><div>IPHONE 15</div>',$html);$this->assertStringContainsString('<span>IMEI Number</span><div>123</div>',$html);$this->assertStringContainsString('<span>iCloud Status</span><div>Clean</div>',$html);}
 public function test_existing_rich_vertical_html_is_preserved():void{$html='<p style="color:red">Important</p><ul><li>One</li></ul>';$this->assertSame($html,ServiceDescriptionPresenter::format($html));}
 public function test_generated_plain_text_is_escaped():void{$html=ServiceDescriptionPresenter::format('Model: <script>alert(1)</script>iPhone IMEI Number: 123');$this->assertStringNotContainsString('<script>',$html);$this->assertStringContainsString('alert(1)iPhone',$html);}
}
