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
use App\Support\CustomerOverview;
use App\Services\Settings\AppSettings;
use Illuminate\Support\Facades\Hash;
use App\Services\Auth\Totp;
use Illuminate\Support\Facades\Schema;

final class PortalController extends Controller
{
    public function dashboard(Request $request, CustomerOverview $overview)
    {
        $orders=$this->orders($request->user()->id);
        $payments=PaymentTransaction::query()->where('user_id',$request->user()->id)->latest()->limit(5)->get();
        $stats=['progress'=>$orders->whereIn('status',['inprogress','In Progress','in progress','processing'])->count(),'completed'=>$orders->whereIn('status',['success','Success','Completed','completed'])->count(),'waiting'=>$orders->whereIn('status',['Waiting','waiting'])->count(),'total'=>$orders->count()];
        $financial=['locked'=>$overview->lockedAmount((int)$request->user()->id),'receipts'=>$overview->totalReceipts((int)$request->user()->id)];
        return view('customer.dashboard',['recentOrders'=>$orders->take(6),'payments'=>$payments,'stats'=>$stats,'financial'=>$financial]);
    }
    public function ordersIndex(Request $request) { return view('customer.orders',['orders'=>$this->orders($request->user()->id)]); }
    public function services(Request $request)
    {
        $settings=app(AppSettings::class);
        $q=trim((string)$request->input('q'));
        $selectedType=trim((string)$request->input('type'));
        $types=collect(['imei'=>ImeiService::class,'server'=>ServerService::class,'file'=>FileService::class,'smm'=>SmmService::class])->filter(fn($model,$type)=>(bool)$settings->get('general.service_'.$type.'_enabled',true))->all();
        $services=collect();
        foreach($types as $type=>$model) {
            if($selectedType!=='' && $selectedType!==$type) continue;
            $services=$services->concat($model::query()->where('active',true)->when($q,fn($x)=>$x->where('name','like','%'.$q.'%'))->orderBy('name')->limit(75)->get()->map(fn($row)=>['type'=>$type,'id'=>$row->id,'name'=>$row->name_text ?? $row->name,'price'=>$row->price ?? ((float)($row->cost ?? 0)+(float)($row->profit ?? 0)),'delivery'=>$row->delivery_time ?? $row->time_text ?? null]));
        }
        $showPrices=auth()->check() || (bool)$settings->get('general.show_prices_to_guests',false);
        return view('customer.services',compact('services','q','selectedType','types','showPrices'));
    }
    public function store() { $settings=app(AppSettings::class); abort_unless((bool)$settings->get('general.store_enabled',true),404); $groupId=auth()->user()?->group_id; $products=Product::query()->where('active',true)->with(['category','groupPrices'=>fn($q)=>$q->when($groupId,fn($x)=>$x->where('group_id',$groupId))])->orderByDesc('hot')->orderBy('ordering')->paginate(20); $schemas=[]; foreach($products as $product){if($product->source_type==='service'&&$product->service_type&&$product->service_id){$service=\App\Support\ProductService::find($product->service_type,(int)$product->service_id,true);if($service)$schemas[$product->id]=\App\Support\ProductService::inputSchema($product->service_type,$service);}} $showPrices=auth()->check()||(bool)$settings->get('general.show_prices_to_guests',false); return view('customer.store',compact('products','schemas','showPrices')); }
    public function downloads() { return view('customer.downloads',['downloads'=>Download::query()->where('active',true)->with('category')->orderByDesc('created_at')->paginate(20)]); }
    public function profile(Request $request, Totp $totp)
    {
        $setup=$request->session()->get('authenticator_setup_secret');
        if(is_array($setup) && ($setup['expires_at']??0)>=now()->timestamp && filled($setup['secret']??null)) {
            $secret=(string)$setup['secret'];
            $authenticatorSetup=['secret'=>$secret,'uri'=>$totp->uri($secret,$request->user()->email,config('app.name','GSM MIX'))];
        } else {
            $request->session()->forget('authenticator_setup_secret');
            $authenticatorSetup=null;
        }
        $passkeys=Schema::hasTable('passkeys')
            ? $request->user()->passkeys()->latest()->get()
            : collect();
        return view('customer.profile',['user'=>$request->user(),'authenticatorSetup'=>$authenticatorSetup,'passkeys'=>$passkeys]);
    }
    public function updateProfile(Request $request)
    {
        $user=$request->user();
        $data=$request->validate(['name'=>'required|string|max:120','email'=>['required','email:rfc','max:255',Rule::unique('users')->ignore($user)],'username'=>['required','alpha_dash','min:3','max:60',Rule::unique('users')->ignore($user)]]);
        $user->update($data); return back()->with('ok','Profile updated.');
    }
    public function updateTwoFactor(Request $request, AppSettings $settings)
    {
        abort_if((bool) $settings->get('general.two_factor_enabled', false), 409, 'Two-step verification is required by the administrator and cannot be disabled.');
        $passwordRules=$request->user()->google_id ? ['nullable','string'] : ['required','current_password:web'];
        $data=$request->validate(['password'=>$passwordRules,'enabled'=>['required','boolean']]);
        $request->session()->forget('authenticator_setup_secret');
        $request->user()->forceFill([
            'two_factor_enabled'=>(bool)$data['enabled'],
            'two_factor_method'=>'email',
            'two_factor_secret'=>null,
            'two_factor_confirmed_at'=>null,
        ])->save();
        return back()->with('ok', $data['enabled'] ? 'Two-step verification enabled.' : 'Two-step verification disabled.');
    }
    public function setupAuthenticator(Request $request, Totp $totp)
    {
        $rules=$request->user()->google_id?['nullable','string']:['required','current_password:web'];
        $request->validate(['password'=>$rules]);
        $secret=$totp->secret();
        $request->session()->put('authenticator_setup_secret',['secret'=>$secret,'expires_at'=>now()->addMinutes(10)->timestamp]);
        return back();
    }
    public function confirmAuthenticator(Request $request, Totp $totp)
    {
        $data=$request->validate(['code'=>['required','digits:6']]);
        $setup=$request->session()->get('authenticator_setup_secret');
        $secret=is_array($setup)?(string)($setup['secret']??''):'';
        if($secret===''||($setup['expires_at']??0)<now()->timestamp||!$totp->verify($secret,$data['code'])) throw \Illuminate\Validation\ValidationException::withMessages(['code'=>'The authenticator setup expired or the code is incorrect.']);
        $request->user()->forceFill(['two_factor_enabled'=>true,'two_factor_method'=>'authenticator','two_factor_secret'=>$secret,'two_factor_confirmed_at'=>now()])->save();
        $request->session()->forget('authenticator_setup_secret');
        return back()->with('ok','Authenticator app enabled.');
    }
    public function removeAuthenticator(Request $request, Totp $totp, AppSettings $settings)
    {
        $rules=$request->user()->google_id?['nullable','string']:['required','current_password:web'];
        $data=$request->validate(['password'=>$rules,'code'=>['required','digits:6']]);
        if(!filled($request->user()->two_factor_secret)||!$totp->verify($request->user()->two_factor_secret,$data['code'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['code'=>'The authenticator code is incorrect.']);
        }
        $request->session()->forget('authenticator_setup_secret');
        $request->user()->forceFill([
            'two_factor_enabled'=>(bool)$settings->get('general.two_factor_enabled',false),
            'two_factor_method'=>'email',
            'two_factor_secret'=>null,
            'two_factor_confirmed_at'=>null,
        ])->save();
        return back()->with('ok','Authenticator app removed.');
    }
    private function orders(int $userId): Collection
    {
        $overview=app(CustomerOverview::class);
        $sets=[['imei',ImeiOrder::class],['server',ServerOrder::class],['file',FileOrder::class],['smm',SmmOrder::class],['product',ProductOrder::class]];
        return collect($sets)->flatMap(fn($set)=>$set[1]::query()->where('user_id',$userId)->latest()->limit(100)->get()->map(fn($o)=>['id'=>$o->id,'type'=>$set[0],'service'=>$overview->serviceName($o,$set[0]),'device'=>$o->device ?? '—','status'=>$o->status,'amount'=>$overview->orderAmount($o),'created_at'=>$o->created_at]))->sortByDesc('created_at')->values();
    }
}
