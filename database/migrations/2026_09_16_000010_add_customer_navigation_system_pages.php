<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { if(!Schema::hasTable('pages'))return; $languageId=DB::table('languages')->where('is_default',true)->value('id')??DB::table('languages')->value('id'); foreach([['home','Home',10,false],['downloads','Downloads',40,false],['place-order','Place order',50,true],['orders','Orders',60,true],['account','Account',70,true]] as [$slug,$title,$ordering,$members]){$id=DB::table('pages')->where('slug',$slug)->value('id');$values=['status'=>'published','placement'=>'header','system'=>true,'authenticated_only'=>$members,'ordering'=>$ordering,'published_at'=>now(),'updated_at'=>now()];if($id)DB::table('pages')->where('id',$id)->update($values);else $id=DB::table('pages')->insertGetId($values+['slug'=>$slug,'open_new_window'=>false,'created_at'=>now()]);if($languageId)DB::table('page_translations')->updateOrInsert(['page_id'=>$id,'language_id'=>$languageId],['title'=>$title,'seo_title'=>$title.' | '.config('app.name'),'updated_at'=>now(),'created_at'=>now()]);}}
 public function down(): void {}
};
