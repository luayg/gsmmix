<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Menu;
use App\Models\Page;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class PageController extends Controller
{
    public function index(Request $request)
    {
        $pages=Page::query()->with(['translations.language','parent'])->when($request->filled('q'),fn($q)=>$q->where(fn($q)=>$q->where('slug','like','%'.$request->string('q').'%')->orWhereHas('translations',fn($t)=>$t->where('title','like','%'.$request->string('q').'%'))))->when($request->filled('status'),fn($q)=>$q->where('status',$request->input('status')))->when($request->filled('placement'),fn($q)=>$q->where('placement',$request->input('placement')))->orderBy('ordering')->orderBy('id')->paginate(25)->withQueryString();
        $menus=Menu::query()->with(['items.page.translations'])->orderBy('location')->get();
        return view('admin.pages.index',compact('pages','menus'));
    }
    public function create(){ return $this->form(); }
    public function store(Request $request, HtmlSanitizer $sanitizer): RedirectResponse { $data=$this->validated($request); $page=DB::transaction(function()use($data,$sanitizer){ $page=Page::create($this->pagePayload($data)); $this->replaceTranslations($page,$data['translations'],$sanitizer); return $page; }); return redirect()->route('admin.pages.edit',$page)->with('ok','Page created.'); }
    public function edit(Page $page){ $page->load('translations'); return $this->form($page); }
    public function update(Request $request, Page $page, HtmlSanitizer $sanitizer): RedirectResponse { if($page->system){$request->merge(['slug'=>$page->slug,'placement'=>$page->placement,'parent_id'=>$page->parent_id,'authenticated_only'=>(int)$page->authenticated_only,'ordering'=>$page->ordering]);} $data=$this->validated($request,$page); DB::transaction(function()use($data,$page,$sanitizer){$page->update($this->pagePayload($data,$page));$this->replaceTranslations($page,$data['translations'],$sanitizer);}); return back()->with('ok','Page updated.'); }
    public function destroy(Page $page): RedirectResponse { if($page->system) return back()->withErrors(['page'=>'System pages cannot be deleted.']); $page->delete(); return redirect()->route('admin.pages.index')->with('ok','Page deleted.'); }
    public function preview(Page $page){ $page->load('translations.language'); return view('admin.pages.preview',compact('page')); }
    private function form(?Page $page=null){ return view('admin.pages.form',['page'=>$page,'languages'=>Language::query()->where('active',true)->orderBy('ordering')->get(),'parents'=>Page::query()->when($page,fn($q)=>$q->where('id','!=',$page->id))->orderBy('ordering')->get()]); }
    private function validated(Request $request,?Page $page=null): array { return $request->validate(['slug'=>['required','string','max:191','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',Rule::unique('pages','slug')->ignore($page)],'status'=>'required|in:draft,published','placement'=>'required|in:header,footer,home,standalone','parent_id'=>'nullable|exists:pages,id|not_in:'.($page?->id??0),'authenticated_only'=>'nullable|boolean','open_new_window'=>'nullable|boolean','ordering'=>'nullable|integer|min:0|max:100000','published_at'=>'nullable|date','featured_image'=>'nullable|string|max:255','og_image'=>'nullable|string|max:255','translations'=>'required|array|min:1','translations.*.language_id'=>'required|distinct|exists:languages,id','translations.*.title'=>'required|string|max:255','translations.*.content'=>'nullable|string|max:1000000','translations.*.seo_title'=>'nullable|string|max:255','translations.*.seo_description'=>'nullable|string|max:1000']); }
    private function pagePayload(array $data, ?Page $page=null): array { $system=$page?->system===true; return ['slug'=>$system?$page->slug:$data['slug'],'status'=>$data['status'],'placement'=>$system?$page->placement:$data['placement'],'parent_id'=>$system?$page->parent_id:($data['parent_id']??null),'featured_image'=>$data['featured_image']??null,'og_image'=>$data['og_image']??null,'authenticated_only'=>$system?$page->authenticated_only:(bool)($data['authenticated_only']??false),'open_new_window'=>(bool)($data['open_new_window']??false),'ordering'=>$system?$page->ordering:(int)($data['ordering']??0),'published_at'=>$data['status']==='published'?($data['published_at']??$page?->published_at??now()):($data['published_at']??null)]; }
    private function replaceTranslations(Page $page,array $rows,HtmlSanitizer $sanitizer): void { foreach($rows as $row)$page->translations()->updateOrCreate(['language_id'=>$row['language_id']],['title'=>$row['title'],'content'=>$sanitizer->clean($row['content']??null),'seo_title'=>$row['seo_title']??null,'seo_description'=>$row['seo_description']??null]); $page->translations()->whereNotIn('language_id',array_column($rows,'language_id'))->delete(); }
}
