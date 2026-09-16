<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
final class MenuController extends Controller
{
    public function store(Request $request): RedirectResponse { $data=$request->validate(['name'=>'required|string|max:100','location'=>'required|string|max:50|regex:/^[a-z0-9_-]+$/|unique:menus,location','active'=>'nullable|boolean']); Menu::create([...$data,'active'=>$request->boolean('active')]); return back()->with('ok','Menu created.'); }
    public function update(Request $request,Menu $menu): RedirectResponse { $data=$request->validate(['name'=>'required|string|max:100','location'=>['required','string','max:50','regex:/^[a-z0-9_-]+$/',Rule::unique('menus')->ignore($menu)],'active'=>'nullable|boolean']); $menu->update([...$data,'active'=>$request->boolean('active')]); return back()->with('ok','Menu updated.'); }
    public function destroy(Menu $menu): RedirectResponse { $menu->delete(); return back()->with('ok','Menu deleted.'); }
    public function storeItem(Request $request,Menu $menu): RedirectResponse { $data=$request->validate(['page_id'=>'nullable|exists:pages,id','parent_id'=>'nullable|exists:menu_items,id','label'=>'required|string|max:100','url'=>'nullable|required_without:page_id|string|max:2048','open_new_window'=>'nullable|boolean','ordering'=>'nullable|integer|min:0|max:100000']); if(($data['parent_id']??null)&&!$menu->items()->whereKey($data['parent_id'])->exists()) abort(422,'Parent item must belong to the same menu.'); $menu->items()->create([...$data,'open_new_window'=>$request->boolean('open_new_window'),'ordering'=>(int)($data['ordering']??0)]); return back()->with('ok','Menu item added.'); }
    public function destroyItem(Menu $menu,MenuItem $item): RedirectResponse { abort_unless($item->menu_id===$menu->id,404); $item->delete(); return back()->with('ok','Menu item deleted.'); }
}
