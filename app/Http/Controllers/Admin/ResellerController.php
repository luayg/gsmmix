<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Rules\SafeRasterImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
final class ResellerController extends Controller {
 public function index(){return view('admin.settings.resellers',['resellers'=>Reseller::query()->orderBy('ordering')->orderBy('name')->paginate(25)]);}
 public function store(Request $r){$d=$this->data($r);if($r->hasFile('image'))$d['image_path']=$r->file('image')->store('resellers','public');Reseller::create($d);return back()->with('ok','Reseller created.');}
 public function update(Request $r,Reseller $reseller){$d=$this->data($r);if($r->hasFile('image')){$old=$reseller->image_path;$d['image_path']=$r->file('image')->store('resellers','public');if($old)Storage::disk('public')->delete($old);}$reseller->update($d);return back()->with('ok','Reseller updated.');}
 public function destroy(Reseller $reseller){if($reseller->image_path)Storage::disk('public')->delete($reseller->image_path);$reseller->delete();return back()->with('ok','Reseller deleted.');}
 private function data(Request $r):array{$d=$r->validate(['name'=>'required|string|max:150','phone'=>'required|string|max:50','whatsapp'=>'nullable|string|max:50','country'=>'nullable|string|max:100','description'=>'nullable|string|max:2000','ordering'=>'nullable|integer|min:0|max:100000','active'=>'nullable|boolean','image'=>['nullable','file','max:2048',new SafeRasterImage]]);$d['active']=$r->boolean('active');$d['ordering']=(int)($d['ordering']??0);return $d;}
}
