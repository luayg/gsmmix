<?php
namespace Tests\Unit;
use App\Support\CustomerOrderResultPresenter;
use PHPUnit\Framework\TestCase;
class CustomerOrderResultPresenterTest extends TestCase
{
 public function test_empty_response_has_no_customer_facing_result():void{$this->assertSame(['image'=>null,'items'=>[],'text'=>null],CustomerOrderResultPresenter::present(null));}
 public function test_structured_result_preserves_image_and_rows():void{$r=CustomerOrderResultPresenter::present(['result_image'=>'https://example.test/phone.png','result_items'=>[['label'=>'iCloud Status','value'=>'Clean']],'result_text'=>'ignored']);$this->assertSame('https://example.test/phone.png',$r['image']);$this->assertSame([['label'=>'iCloud Status','value'=>'Clean']],$r['items']);$this->assertNull($r['text']);}
 public function test_legacy_long_result_is_split_into_rows():void{$r=CustomerOrderResultPresenter::present('Model: iPhone 15 Pro IMEI Number: 123456789012345 Find My iPhone: OFF iCloud Status: Clean SIM-Lock Status: Unlocked');$this->assertSame(['label'=>'Model','value'=>'iPhone 15 Pro'],$r['items'][0]);$this->assertContains(['label'=>'iCloud Status','value'=>'Clean'],$r['items']);$this->assertContains(['label'=>'SIM-Lock Status','value'=>'Unlocked'],$r['items']);$this->assertNull($r['text']);}
 public function test_unsafe_image_is_not_exposed():void{$r=CustomerOrderResultPresenter::present(['result_image'=>'javascript:alert(1)','result_text'=>'Delivered']);$this->assertNull($r['image']);$this->assertSame('Delivered',$r['text']);}
}
