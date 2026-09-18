<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
final class MenuController extends Controller
{
    public function store(Request $request): RedirectResponse { $data=$request->validate(['name'=>'required|string|max:100','location'=>'required|string|max:50|regex:/^[a-z0-9_-]+$/|unique:menus,location','active'=>'nullable|boolean']); Menu::create([...$data,'active'=>$request->boolean('active')]); return back()->with('ok','Menu created.'); }
    public function update(Request $request,Menu $menu): RedirectResponse { $data=$request->validate(['name'=>'required|string|max:100','location'=>['required','string','max:50','regex:/^[a-z0-9_-]+$/',Rule::unique('menus')->ignore($menu)],'active'=>'nullable|boolean']); $menu->update([...$data,'active'=>$request->boolean('active')]); return back()->with('ok','Menu updated.'); }
    public function destroy(Menu $menu): RedirectResponse { $menu->delete(); return back()->with('ok','Menu deleted.'); }
    public function storeItem(Request $request,Menu $menu): RedirectResponse
    {
        $data=$request->validate([
            'page_id'=>'nullable|exists:pages,id','parent_id'=>'nullable|exists:menu_items,id',
            'label'=>'nullable|required_without:page_id|string|max:100',
            'url'=>['nullable','string','max:2048','regex:/^(https?:\\/\\/|\\/)/i'],
            'open_new_window'=>'nullable|boolean','ordering'=>'nullable|integer|min:0|max:100000',
        ]);
        if(($data['parent_id']??null)&&!$menu->items()->whereKey($data['parent_id'])->exists()) abort(422,'Parent item must belong to the same menu.');
        if(!empty($data['page_id'])){
            $page=\App\Models\Page::with('translations')->findOrFail($data['page_id']);
            $data['label']=filled($data['label']??null)
                ? $data['label']
                : ($page->translations->first()?->title??str($page->slug)->replace('-',' ')->title()->toString());
            $data['url']=null;
        }
        $data['ordering']=(int)($data['ordering']??(($menu->items()->max('ordering')??0)+10));
        $menu->items()->create([...$data,'open_new_window'=>$request->boolean('open_new_window')]);
        return back()->with('ok','Menu item added.');
    }
    public function updateItem(Request $request,Menu $menu,MenuItem $item): RedirectResponse
    {
        abort_unless($item->menu_id===$menu->id,404);
        $data=$request->validate(['label'=>'required|string|max:100','url'=>['nullable','string','max:2048','regex:/^(https?:\\/\\/|\\/)/i'],'open_new_window'=>'nullable|boolean']);
        $item->update(['label'=>$data['label'],'url'=>$item->page_id?null:($data['url']??null),'open_new_window'=>$request->boolean('open_new_window')]);
        return back()->with('ok','Menu item updated.');
    }
    public function reorder(Request $request,Menu $menu): \Illuminate\Http\JsonResponse
    {
        $data=$request->validate(['items'=>'required|array|max:250','items.*.id'=>'required|integer|distinct','items.*.parent_id'=>'nullable|integer','items.*.ordering'=>'required|integer|min:0|max:100000']);
        $ids=$menu->items()->pluck('id');
        abort_unless(collect($data['items'])->pluck('id')->sort()->values()->all()===$ids->sort()->values()->all(),422,'The menu tree is incomplete.');
        $allowed=$ids->flip();
        foreach($data['items'] as $row) abort_if($row['parent_id']!==null && (!$allowed->has($row['parent_id']) || $row['parent_id']===$row['id']),422,'Invalid parent menu item.');
        $parents=collect($data['items'])->mapWithKeys(fn($row)=>[$row['id']=>$row['parent_id']]);
        foreach($parents as $id=>$parent){$seen=[$id=>true];while($parent!==null){abort_if(isset($seen[$parent]),422,'Circular menu branches are not allowed.');$seen[$parent]=true;$parent=$parents->get($parent);}}
        DB::transaction(fn()=>collect($data['items'])->each(fn($row)=>$menu->items()->whereKey($row['id'])->update(['parent_id'=>$row['parent_id'],'ordering'=>$row['ordering']])));
        return response()->json(['ok'=>true]);
    }
    public function destroyItem(Menu $menu,MenuItem $item): RedirectResponse { abort_unless($item->menu_id===$menu->id,404); $item->delete(); return back()->with('ok','Menu item deleted.'); }
}
