<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Product;
use App\Models\ImeiService;
use App\Models\ServerService;
use App\Models\FileService;
use App\Models\SmmService;

final class PublicSiteController extends Controller
{
    public function home()
    {
        $page=$this->page('home');
        $products=Product::query()->where('active',true)->orderByDesc('new')->orderBy('ordering')->limit(8)->get();
        $counts=['imei'=>ImeiService::where('active',true)->count(),'server'=>ServerService::where('active',true)->count(),'file'=>FileService::where('active',true)->count(),'smm'=>SmmService::where('active',true)->count()];
        return view('site.home',compact('page','products','counts'));
    }
    public function page(Page $page)
    {
        abort_unless($page->status==='published' && (!$page->published_at || $page->published_at->isPast()),404);
        if($page->authenticated_only && !auth()->check()) return redirect()->guest(route('login'));
        $page->load('translations.language');
        return view('site.page',compact('page'));
    }
    private function page(string $slug): ?Page { return Page::query()->where('slug',$slug)->with('translations.language')->first(); }
}
