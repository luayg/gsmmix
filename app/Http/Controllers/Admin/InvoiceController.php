<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Settings\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $payments=PaymentTransaction::query()->with(['user','gateway','invoice'])->where('status','paid')->when($request->filled('q'),fn($q)=>$q->where(fn($q)=>$q->where('uuid','like','%'.$request->string('q').'%')->orWhere('external_id','like','%'.$request->string('q').'%')->orWhereHas('invoice',fn($i)=>$i->where('number','like','%'.$request->string('q').'%'))->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$request->string('q').'%')->orWhere('email','like','%'.$request->string('q').'%'))))->when($request->integer('user_id'),fn($q,$id)=>$q->where('user_id',$id))->when($request->integer('gateway_id'),fn($q,$id)=>$q->where('payment_gateway_id',$id))->when($request->filled('from'),fn($q)=>$q->whereDate(DB::raw('COALESCE(paid_at, created_at)'),'>=',$request->input('from')))->when($request->filled('to'),fn($q)=>$q->whereDate(DB::raw('COALESCE(paid_at, created_at)'),'<=',$request->input('to')))->orderByDesc(DB::raw('COALESCE(paid_at, created_at)'))->paginate(25)->withQueryString();
        $users=User::query()->orderBy('name')->get(['id','name','email']);
        $gateways=Schema::hasTable('payment_gateways')?PaymentGateway::query()->orderBy('name')->get(['id','name']):collect();
        return view('admin.finances.invoices.index',compact('payments','users','gateways'));
    }
    public function create(){ return view('admin.finances.invoices.form',['invoice'=>null,'users'=>User::query()->orderBy('name')->get(['id','name','email']),'currencies'=>Currency::query()->where('active',true)->orderBy('ordering')->get()]); }
    public function store(Request $request, AppSettings $settings): RedirectResponse
    {
        $data=$this->validated($request); $user=User::findOrFail($data['user_id']);
        $invoice=DB::transaction(function() use($data,$user,$settings){
            $invoice=Invoice::create(['number'=>'INV-'.now()->format('Ymd').'-'.strtoupper(Str::ulid()->toBase32()),'user_id'=>$user->id,'status'=>$data['status'],'currency_code'=>$data['currency_code'],'exchange_rate'=>$data['exchange_rate'],'customer_snapshot'=>['name'=>$user->name,'email'=>$user->email,'username'=>$user->username],'company_snapshot'=>['name'=>$settings->get('general.site_name',config('app.name')),'email'=>$settings->get('general.email')],'issued_at'=>$data['issued_at']??null,'due_at'=>$data['due_at']??null,'notes'=>$data['notes']??null,'terms'=>$data['terms']??null]);
            $this->replaceItems($invoice,$data['items'],$data); return $invoice;
        });
        return redirect()->route('admin.finances.invoices.show',$invoice)->with('ok','Invoice created.');
    }
    public function show(Invoice $invoice){ $invoice->load('user','items','payments'); return view('admin.finances.invoices.show',compact('invoice')); }
    public function edit(Invoice $invoice){ abort_if(!in_array($invoice->status,['draft','pending']),409,'Issued financial records cannot be edited.'); $invoice->load('items'); return view('admin.finances.invoices.form',['invoice'=>$invoice,'users'=>User::query()->orderBy('name')->get(['id','name','email']),'currencies'=>Currency::query()->where('active',true)->orderBy('ordering')->get()]); }
    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_if(!in_array($invoice->status,['draft','pending']),409,'Issued financial records cannot be edited.'); $data=$this->validated($request);
        DB::transaction(function() use($invoice,$data){ $invoice->update(['status'=>$data['status'],'currency_code'=>$data['currency_code'],'exchange_rate'=>$data['exchange_rate'],'issued_at'=>$data['issued_at']??null,'due_at'=>$data['due_at']??null,'notes'=>$data['notes']??null,'terms'=>$data['terms']??null]); $this->replaceItems($invoice,$data['items'],$data); });
        return redirect()->route('admin.finances.invoices.show',$invoice)->with('ok','Invoice updated.');
    }
    public function destroy(Invoice $invoice): RedirectResponse { abort_if($invoice->status!=='draft'||$invoice->payments()->exists(),409,'Only unpaid drafts can be deleted.'); $invoice->delete(); return redirect()->route('admin.finances.invoices.index')->with('ok','Draft deleted.'); }
    public function print(Invoice $invoice){ $invoice->load('user','items','payments'); return view('admin.finances.invoices.print',compact('invoice')); }
    public function printPayment(PaymentTransaction $payment){ $payment->load('user','gateway','invoice'); return view('admin.finances.invoices.payment-print',compact('payment')); }
    public function addPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $data=$request->validate(['amount'=>'required|decimal:0,4|gt:0','reference'=>'required|string|max:191','paid_at'=>'required|date']);
        DB::transaction(function() use($invoice,$data): void {
            $locked=Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_if(in_array($locked->status,['draft','cancelled','refunded']),409,'Payments cannot be attached to this invoice.');
            $remaining=bcsub((string)$locked->total,(string)$locked->paid_total,4);
            if(bccomp((string)$data['amount'],$remaining,4)>0) throw ValidationException::withMessages(['amount'=>'Payment exceeds the invoice balance.']);
            $locked->payments()->create(['amount'=>$data['amount'],'currency_code'=>$locked->currency_code,'exchange_rate'=>$locked->exchange_rate,'reference'=>$data['reference'],'paid_at'=>$data['paid_at']]);
            $paid=bcadd((string)$locked->paid_total,(string)$data['amount'],4);
            $complete=bccomp($paid,(string)$locked->total,4)>=0;
            $locked->update(['paid_total'=>$paid,'status'=>$complete?'paid':'partially_paid','paid_at'=>$complete?$data['paid_at']:null]);
        });
        return back()->with('ok','Payment recorded against the invoice.');
    }
    public function cancel(Invoice $invoice): RedirectResponse
    {
        abort_if(bccomp((string)$invoice->paid_total,'0',4)>0,409,'Paid invoices require a refund or credit note.');
        abort_if(in_array($invoice->status,['cancelled','refunded']),409,'Invoice is already closed.');
        $invoice->update(['status'=>'cancelled']);
        return back()->with('ok','Invoice cancelled without deleting its history.');
    }
    private function validated(Request $request): array { return $request->validate(['user_id'=>'required|exists:users,id','status'=>['required',Rule::in(['draft','pending'])],'currency_code'=>'required|string|size:3|exists:currencies,code','exchange_rate'=>'required|decimal:0,8|gt:0','issued_at'=>'nullable|date','due_at'=>'nullable|date|after_or_equal:issued_at','discount_total'=>'nullable|decimal:0,4|min:0','fee_total'=>'nullable|decimal:0,4|min:0','notes'=>'nullable|string|max:10000','terms'=>'nullable|string|max:10000','items'=>'required|array|min:1|max:100','items.*.description'=>'required|string|max:255','items.*.quantity'=>'required|decimal:0,4|gt:0','items.*.unit_price'=>'required|decimal:0,4|min:0','items.*.discount'=>'nullable|decimal:0,4|min:0','items.*.tax_rate'=>'nullable|decimal:0,4|between:0,100']); }
    private function replaceItems(Invoice $invoice,array $items,array $data): void
    {
        $invoice->items()->delete(); $subtotal='0'; $tax='0';
        foreach(array_values($items) as $i=>$row){ $gross=bcmul((string)$row['quantity'],(string)$row['unit_price'],4); $discount=(string)($row['discount']??0); if(bccomp($discount,$gross,4)>0) throw ValidationException::withMessages(["items.$i.discount"=>'Discount cannot exceed the line amount.']); $net=bcsub($gross,$discount,4); $lineTax=bcdiv(bcmul($net,(string)($row['tax_rate']??0),6),'100',4); $line=bcadd($net,$lineTax,4); $invoice->items()->create(['description'=>$row['description'],'quantity'=>$row['quantity'],'unit_price'=>$row['unit_price'],'discount'=>$discount,'tax_rate'=>$row['tax_rate']??0,'line_total'=>$line,'ordering'=>$i]); $subtotal=bcadd($subtotal,$gross,4); $tax=bcadd($tax,$lineTax,4); }
        $discount=(string)($data['discount_total']??0); $fee=(string)($data['fee_total']??0); $total=bcadd(bcsub(bcadd($subtotal,$tax,4),$discount,4),$fee,4); if(bccomp($total,'0',4)<0) throw ValidationException::withMessages(['discount_total'=>'Invoice total cannot be negative.']); $invoice->update(['subtotal'=>$subtotal,'discount_total'=>$discount,'tax_total'=>$tax,'fee_total'=>$fee,'total'=>$total]);
    }
}
