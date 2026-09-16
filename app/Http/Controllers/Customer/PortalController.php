<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\FileOrder;
use App\Models\FileService;
use App\Models\ImeiOrder;
use App\Models\ImeiService;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ServerOrder;
use App\Models\ServerService;
use App\Models\SmmOrder;
use App\Models\SmmService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

final class PortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $orders=$this->orders($request->user()->id);
        $payments=PaymentTransaction::query()->where('user_id',$request->user()->id)->latest()->limit(5)->get();
        $stats=['progress'=>$orders->whereIn('status',['In Progress','in progress','processing'])->count(),'completed'=>$orders->whereIn('status',['Completed','completed'])->count(),'waiting'=>$orders->whereIn('status',['Waiting','waiting'])->count(),'total'=>$orders->count()];
        return view('customer.dashboard',['recentOrders'=>$orders->take(6),'payments'=>$payments,'stats'=>$stats]);
    }
    public function ordersIndex(Request $request) { return view('customer.orders',['orders'=>$this->orders($request->user()->id)]); }
    public function services(Request $request)
    {
        $q=trim((string)$request->input('q'));
        $selectedType=trim((string)$request->input('type'));
        $types=['imei'=>ImeiService::class,'server'=>ServerService::class,'file'=>FileService::class,'smm'=>SmmService::class];
        $services=collect();
        foreach($types as $type=>$model) {
            if($selectedType!=='' && $selectedType!==$type) continue;
            $services=$services->concat($model::query()->where('active',true)->when($q,fn($x)=>$x->where('name','like','%'.$q.'%'))->orderBy('name')->limit(75)->get()->map(fn($row)=>['type'=>$type,'id'=>$row->id,'name'=>$row->name_text ?? $row->name,'price'=>$row->price ?? ((float)($row->cost ?? 0)+(float)($row->profit ?? 0)),'delivery'=>$row->delivery_time ?? $row->time_text ?? null]));
        }
        return view('customer.services',compact('services','q','selectedType'));
    }
    public function store() { $groupId=auth()->user()?->group_id; $products=Product::query()->where('active',true)->with(['category','groupPrices'=>fn($q)=>$q->when($groupId,fn($x)=>$x->where('group_id',$groupId))])->orderByDesc('hot')->orderBy('ordering')->paginate(20); $schemas=[]; foreach($products as $product){if($product->source_type==='service'&&$product->service_type&&$product->service_id){$service=\App\Support\ProductService::find($product->service_type,(int)$product->service_id,true);if($service)$schemas[$product->id]=\App\Support\ProductService::inputSchema($product->service_type,$service);}} return view('customer.store',compact('products','schemas')); }
    public function downloads() { return view('customer.downloads',['downloads'=>Download::query()->where('active',true)->with('category')->orderByDesc('created_at')->paginate(20)]); }
    public function profile(Request $request) { return view('customer.profile',['user'=>$request->user()]); }
    public function updateProfile(Request $request)
    {
        $user=$request->user();
        $data=$request->validate(['name'=>'required|string|max:120','email'=>['required','email:rfc','max:255',Rule::unique('users')->ignore($user)],'username'=>['required','alpha_dash','min:3','max:60',Rule::unique('users')->ignore($user)]]);
        $user->update($data); return back()->with('ok','Profile updated.');
    }
    private function orders(int $userId): Collection
    {
        $sets=[['imei',ImeiOrder::class],['server',ServerOrder::class],['file',FileOrder::class],['smm',SmmOrder::class],['product',ProductOrder::class]];
        return collect($sets)->flatMap(fn($set)=>$set[1]::query()->where('user_id',$userId)->latest()->limit(100)->get()->map(fn($o)=>['id'=>$o->id,'type'=>$set[0],'service'=>$o->service?->name ?? $o->product?->name ?? ucfirst($set[0]).' order','device'=>$o->device ?? '—','status'=>$o->status,'amount'=>$o->order_price ?? $o->price ?? 0,'created_at'=>$o->created_at]))->sortByDesc('created_at')->values();
    }
}
