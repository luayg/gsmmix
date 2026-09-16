<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pages')) return;
        $languageId = DB::table('languages')->where('is_default', true)->value('id') ?? DB::table('languages')->value('id');
        $pages = [
            ['slug'=>'home','title'=>'Home','placement'=>'home','ordering'=>10],
            ['slug'=>'services','title'=>'Services','placement'=>'header','ordering'=>20],
            ['slug'=>'store','title'=>'Store','placement'=>'header','ordering'=>30],
            ['slug'=>'pricing','title'=>'Pricing','placement'=>'header','ordering'=>40],
            ['slug'=>'support','title'=>'Support','placement'=>'header','ordering'=>50],
            ['slug'=>'privacy','title'=>'Privacy policy','placement'=>'footer','ordering'=>80],
            ['slug'=>'terms','title'=>'Terms and conditions','placement'=>'footer','ordering'=>90],
        ];
        foreach ($pages as $row) {
            $id = DB::table('pages')->where('slug',$row['slug'])->value('id');
            $values = ['status'=>'published','placement'=>$row['placement'],'system'=>true,'ordering'=>$row['ordering'],'published_at'=>now(),'updated_at'=>now()];
            if ($id) DB::table('pages')->where('id',$id)->update($values);
            else { $id = DB::table('pages')->insertGetId($values + ['slug'=>$row['slug'],'authenticated_only'=>false,'open_new_window'=>false,'created_at'=>now()]); }
            if ($languageId) DB::table('page_translations')->updateOrInsert(['page_id'=>$id,'language_id'=>$languageId],['title'=>$row['title'],'seo_title'=>$row['title'].' | '.config('app.name'),'updated_at'=>now(),'created_at'=>now()]);
        }
    }
    public function down(): void { /* System content is preserved on rollback. */ }
};
