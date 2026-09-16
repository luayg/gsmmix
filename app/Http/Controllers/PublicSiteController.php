<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Product;
use App\Models\ImeiService;
use App\Models\ServerService;
use App\Models\FileService;
use App\Models\SmmService;
use Illuminate\Support\Facades\Schema;
use App\Services\Settings\AppSettings;

final class PublicSiteController extends Controller
{
    public function home()
    {
        $page=Schema::hasTable('pages') ? $this->findPage('home') : null;
        $products=Schema::hasTable('products') ? Product::query()->where('active',true)->orderByDesc('new')->orderBy('ordering')->limit(8)->get() : collect();
        $counts=[
            'imei'=>Schema::hasTable('imei_services') ? ImeiService::where('active',true)->count() : 0,
            'server'=>Schema::hasTable('server_services') ? ServerService::where('active',true)->count() : 0,
            'file'=>Schema::hasTable('file_services') ? FileService::where('active',true)->count() : 0,
            'smm'=>Schema::hasTable('smm_services') ? SmmService::where('active',true)->count() : 0,
        ];
        $homeSettings=Schema::hasTable('settings')?app(AppSettings::class)->group('general'):[];
        return view('site.home',compact('page','products','counts','homeSettings'));
    }
    public function page(Page $page)
    {
        abort_unless($page->status==='published' && (!$page->published_at || $page->published_at->isPast()),404);
        if($page->authenticated_only && !auth()->check()) return redirect()->guest(route('login'));
        $page->load('translations.language');
        return view('site.page',compact('page'));
    }
    private function findPage(string $slug): ?Page { return Page::query()->where('slug',$slug)->with('translations.language')->first(); }
}
