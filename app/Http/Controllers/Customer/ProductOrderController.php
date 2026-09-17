<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Rules\SafeOrderFile;
use App\Services\Orders\ProductOrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
final class ProductOrderController extends Controller
{
 public function store(Request $request,ProductOrderService $orders){abort_unless((bool)app(\App\Services\Settings\AppSettings::class)->get('general.store_enabled',true),404);$data=$request->validate(['request_uid'=>'required|uuid','product_id'=>'required|integer|exists:products,id','device'=>'nullable|string|max:2000','quantity'=>'nullable|integer|min:1|max:1000000000','required'=>'nullable|array','file'=>['nullable','file','max:51200',new SafeOrderFile],'comments'=>'nullable|string|max:5000']);$data['user_id']=$request->user()->id;$data['ip']=$request->ip();try{$order=$orders->create($data,(int)$request->user()->id);}catch(UniqueConstraintViolationException){throw ValidationException::withMessages(['request_uid'=>'This submission was already used. Refresh and try again.']);}return response()->json(['ok'=>true,'id'=>$order->id,'status'=>$order->status,'balance'=>$request->user()->fresh()->balance]);}
}
