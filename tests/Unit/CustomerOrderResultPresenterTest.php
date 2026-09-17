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
 public function test_smm_result_only_exposes_start_count_and_complete_status():void{$r=CustomerOrderResultPresenter::present(['message'=>'Provider raw message','start_count'=>125,'remains'=>0],'smm','success');$this->assertSame([['label'=>'Start count','value'=>'125'],['label'=>'Status','value'=>'Complete']],$r['items']);$this->assertNull($r['text']);}
 public function test_smm_result_without_start_count_only_exposes_status():void{$r=CustomerOrderResultPresenter::present(['message'=>'Do not show me'],'smm','success');$this->assertSame([['label'=>'Status','value'=>'Complete']],$r['items']);}
 public function test_incomplete_smm_result_does_not_expose_provider_message():void{$r=CustomerOrderResultPresenter::present(['message'=>'Provider processing details'],'smm','inprogress');$this->assertSame(['image'=>null,'items'=>[],'text'=>null],$r);}
 public function test_latest_admin_reply_is_customer_visible_for_every_order_type():void{foreach(['imei','server','file','smm','product'] as $type){$r=CustomerOrderResultPresenter::present(['result_text'=>'old','provider_reply_html'=>'<p>Latest <strong>reply</strong></p>'],$type,'success');$this->assertSame('Latest reply',$r['text']);$this->assertSame([],$r['items']);}}
}
