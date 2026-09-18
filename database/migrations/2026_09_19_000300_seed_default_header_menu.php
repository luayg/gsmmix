<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('menus') || !Schema::hasTable('menu_items') || !Schema::hasTable('pages')) return;
        if (DB::table('menus')->where('location','header-primary')->exists()) return;

        $now=now();
        $menuId=DB::table('menus')->insertGetId(['name'=>'Public header','location'=>'header-primary','active'=>true,'created_at'=>$now,'updated_at'=>$now]);
        $slugs=['home','services','store','downloads'];
        $pages=DB::table('pages')->whereIn('slug',$slugs)->get()->keyBy('slug');
        foreach($slugs as $position=>$slug){
            $page=$pages->get($slug);
            if(!$page) continue;
            $label=DB::table('page_translations')->where('page_id',$page->id)->orderBy('id')->value('title') ?: ucfirst($slug);
            DB::table('menu_items')->insert(['menu_id'=>$menuId,'page_id'=>$page->id,'parent_id'=>null,'label'=>$label,'url'=>null,'open_new_window'=>false,'ordering'=>($position+1)*10,'created_at'=>$now,'updated_at'=>$now]);
        }
    }

    public function down(): void
    {
        // User-managed navigation is intentionally preserved on rollback.
    }
};
