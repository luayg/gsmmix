<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\PaymentInitiator;
use App\Services\Payments\PaymentQuote;
use App\Services\Payments\PaymentSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PaymentController extends Controller
{
    public function index(Request $request)
    {
        return view('customer.payments.index',['transactions'=>PaymentTransaction::query()->where('user_id',$request->user()->id)->with('gateway')->latest()->paginate(20)]);
    }
    public function create()
    {
        return view('customer.payments.create',['gateways'=>PaymentGateway::query()->where('active',true)->with('currencies')->orderByDesc('is_system')->orderBy('ordering')->get(),'currency'=>Currency::query()->where('active',true)->where('is_default',true)->first() ?? Currency::query()->where('active',true)->first()]);
    }
    public function store(Request $request, PaymentInitiator $initiator, PaymentQuote $quote)
    {
        $data=$request->validate(['amount'=>'required|decimal:0,8|gt:0','gateway_id'=>'required|exists:payment_gateways,id','currency_id'=>'required|exists:currencies,id','proof'=>'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        $gateway=PaymentGateway::query()->where('active',true)->findOrFail($data['gateway_id']);
        $currency=Currency::query()->where('active',true)->findOrFail($data['currency_id']);
        abort_unless($gateway->currencies()->whereKey($currency->id)->exists(),422,'Currency is not supported by this payment method.');
        if($gateway->is_system){
            $result=$initiator->create($request->user(),$gateway,$currency,$data['amount'],route('customer.payments.paypal.return'),route('customer.payments.create'));
            if($url=data_get($result,'provider.checkout_url')) return redirect()->away($url);
            return redirect()->route('customer.payments.show',$result['payment']);
        }
        $calculated=$quote->calculate($data['amount'],$gateway,$currency);
        $proof=$request->hasFile('proof')?$request->file('proof')->store('payment-proofs','local'):null;
        $payment=PaymentTransaction::create($calculated+['uuid'=>(string)Str::uuid(),'user_id'=>$request->user()->id,'payment_gateway_id'=>$gateway->id,'currency_id'=>$currency->id,'status'=>'review','proof_path'=>$proof,'metadata'=>['instructions'=>$gateway->instructions,'payment_details'=>data_get($gateway->config,'payment_details')]]);
        return redirect()->route('customer.payments.show',$payment)->with('ok','Payment submitted for review.');
    }
    public function show(Request $request, PaymentTransaction $payment)
    {
        abort_unless($payment->user_id===$request->user()->id,404); $payment->load('gateway');
        return view('customer.payments.show',compact('payment'));
    }
    public function status(Request $request, PaymentTransaction $payment)
    {
        abort_unless($payment->user_id===$request->user()->id,404);
        return response()->json(['status'=>$payment->status,'paid_at'=>$payment->paid_at?->toIso8601String(),'balance'=>$request->user()->fresh()->balance]);
    }
    public function paypalReturn(Request $request, PayPalGateway $paypal, PaymentSettlement $settlement)
    {
        $payment=PaymentTransaction::query()->where('user_id',$request->user()->id)->where('uuid',$request->string('payment'))->with('gateway')->firstOrFail();
        abort_unless($payment->gateway?->driver==='paypal',404);
        $capture=$paypal->captureOrder($payment->gateway,(string)$payment->external_id);
        if(($capture['status']??null)==='COMPLETED') $settlement->paid($payment,(string)data_get($capture,'purchase_units.0.payments.captures.0.id',$payment->external_id),['capture'=>$capture]);
        return redirect()->route('customer.payments.show',$payment)->with('ok','PayPal payment processed.');
    }
}
