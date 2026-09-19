<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\FinanceTransaction;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\PaymentInitiator;
use App\Services\Payments\PaymentQuote;
use App\Services\Payments\PaymentSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Rules\SafeRasterImage;

final class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $data=$request->validate(['from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from']);
        $base=FinanceTransaction::query()->where('user_id',$request->user()->id);
        $opening=(clone $base)->when($data['from']??null,fn($q,$v)=>$q->whereDate('created_at','<',$v))->latest('id')->value('balance_after') ?? 0;
        $ledger=$base->when($data['from']??null,fn($q,$v)=>$q->whereDate('created_at','>=',$v))->when($data['to']??null,fn($q,$v)=>$q->whereDate('created_at','<=',$v));
        $summary=['income'=>(clone $ledger)->where('direction','income')->sum('amount'),'expense'=>(clone $ledger)->where('direction','expense')->sum('amount'),'refund'=>(clone $ledger)->where('kind','order_release')->sum('amount')];
        return view('customer.payments.index',['transactions'=>(clone $ledger)->latest('id')->paginate(30)->withQueryString(),'payments'=>PaymentTransaction::query()->where('user_id',$request->user()->id)->with('gateway')->latest()->limit(20)->get(),'summary'=>$summary,'opening'=>$opening]);
    }

    public function printStatement(Request $request)
    {
        $data=$request->validate(['from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from']);
        $base=FinanceTransaction::query()->where('user_id',$request->user()->id);
        $opening=(clone $base)->when($data['from']??null,fn($q,$v)=>$q->whereDate('created_at','<',$v))->latest('id')->value('balance_after') ?? 0;
        $transactions=$base->when($data['from']??null,fn($q,$v)=>$q->whereDate('created_at','>=',$v))->when($data['to']??null,fn($q,$v)=>$q->whereDate('created_at','<=',$v))->oldest('id')->get();
        $summary=['income'=>$transactions->where('direction','income')->sum('amount'),'expense'=>$transactions->where('direction','expense')->sum('amount'),'refund'=>$transactions->where('kind','order_release')->sum('amount')];
        return view('customer.payments.print',compact('transactions','summary','opening'));
    }
    public function create()
    {
        return view('customer.payments.create',['gateways'=>PaymentGateway::query()->where('active',true)->with('currencies')->orderByDesc('is_system')->orderBy('ordering')->get(),'currency'=>Currency::query()->where('active',true)->where('is_default',true)->first() ?? Currency::query()->where('active',true)->first()]);
    }
    public function store(Request $request, PaymentInitiator $initiator, PaymentQuote $quote)
    {
        $proof=$request->file('proof');
        if($proof instanceof UploadedFile&&!$proof->isValid()){
            $message=match($proof->getError()){
                UPLOAD_ERR_INI_SIZE=>'The receipt exceeds the PHP upload_max_filesize limit ('.ini_get('upload_max_filesize').').',
                UPLOAD_ERR_FORM_SIZE=>'The receipt exceeds the form upload limit.',
                UPLOAD_ERR_PARTIAL=>'The receipt was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR=>'The PHP temporary upload folder is missing.',
                UPLOAD_ERR_CANT_WRITE=>'PHP could not write the receipt to its temporary folder.',
                UPLOAD_ERR_EXTENSION=>'A PHP extension stopped the receipt upload.',
                default=>'The receipt failed to upload (PHP error '.$proof->getError().').',
            };
            throw ValidationException::withMessages(['proof'=>$message]);
        }
        $data=$request->validate(['amount'=>'required|decimal:0,8|gt:0','gateway_id'=>'required|exists:payment_gateways,id','currency_id'=>'required|exists:currencies,id','proof'=>['nullable','file','max:5120',new SafeRasterImage]],['proof.uploaded'=>'The receipt failed to upload. The current PHP limit is '.ini_get('upload_max_filesize').'.']);
        $gateway=PaymentGateway::query()->where('active',true)->findOrFail($data['gateway_id']);
        $currency=Currency::query()->where('active',true)->findOrFail($data['currency_id']);
        abort_unless($gateway->currencies()->whereKey($currency->id)->exists(),422,'Currency is not supported by this payment method.');
        if($gateway->is_system){
            $result=$initiator->create($request->user(),$gateway,$currency,$data['amount'],route('customer.payments.paypal.return'),route('customer.payments.create'));
            if($url=data_get($result,'provider.checkout_url')) return redirect()->away($url);
            return redirect()->route('customer.payments.show',$result['payment']);
        }
        if (!$request->hasFile('proof')) {
            throw ValidationException::withMessages(['proof' => 'A transfer receipt image is required for manual payments.']);
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
