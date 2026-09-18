<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\PageTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class PageThemeController extends Controller
{
    public function create(){return view('admin.pages.theme',['theme'=>null]);}
    public function edit(PageTheme $theme){return view('admin.pages.theme',compact('theme'));}
    public function store(Request $request): RedirectResponse{$theme=PageTheme::create(['name'=>$request->validate(['name'=>'required|string|max:80'])['name'],'settings'=>$this->settings($request)]);return redirect()->route('admin.pages.themes.edit',$theme)->with('ok','Theme saved.');}
    public function update(Request $request,PageTheme $theme): RedirectResponse{$theme->update(['name'=>$request->validate(['name'=>'required|string|max:80'])['name'],'settings'=>$this->settings($request)]);return back()->with('ok','Theme updated.');}
    public function activate(PageTheme $theme): RedirectResponse{DB::transaction(function()use($theme){PageTheme::query()->update(['active'=>false]);$theme->update(['active'=>true]);});return back()->with('ok','Theme activated.');}
    public function destroy(PageTheme $theme): RedirectResponse{if($theme->active)return back()->withErrors(['theme'=>'The active theme cannot be deleted. Activate another theme first.']);$theme->delete();return back()->with('ok','Theme deleted.');}
    private function settings(Request $request): array{return $request->validate(['primary'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],'accent'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],'background'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],'surface'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],'text'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],'muted'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],'heading_font'=>['required',Rule::in(['Inter','Arial','Roboto','Tahoma','Georgia'])],'body_font'=>['required',Rule::in(['Inter','Arial','Roboto','Tahoma','Georgia'])],'base_font_size'=>'required|integer|between:13,20','heading_scale'=>'required|numeric|between:0.8,1.5','radius'=>'required|integer|between:0,32']);}
}
