<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;use App\Models\DownloadCategory;use Illuminate\Http\RedirectResponse;use Illuminate\Http\Request;
final class DownloadCategoryController extends Controller {
 public function index(){return view('admin.downloads.categories',['categories'=>DownloadCategory::query()->withCount('downloads')->orderBy('ordering')->paginate(25)]);}
 public function store(Request $r):RedirectResponse{$d=$this->data($r);DownloadCategory::create($d);return back()->with('ok','Category created.');}
 public function update(Request $r,DownloadCategory $category):RedirectResponse{$category->update($this->data($r));return back()->with('ok','Category updated.');}
 public function destroy(DownloadCategory $category):RedirectResponse{if($category->downloads()->exists())return back()->withErrors(['category'=>'Move or delete its downloads first.']);$category->delete();return back()->with('ok','Category deleted.');}
 private function data(Request $r):array{$d=$r->validate(['name'=>'required|string|max:150','description'=>'nullable|string|max:2000','ordering'=>'nullable|integer|min:0|max:100000','required_permission'=>'nullable|string|max:191|exists:permissions,name','active'=>'nullable|boolean']);return [...$d,'active'=>$r->boolean('active'),'ordering'=>(int)($d['ordering']??0)];}
}
