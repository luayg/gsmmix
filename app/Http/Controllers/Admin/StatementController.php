<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceTransaction;
use App\Models\User;
use Illuminate\Http\Request;

final class StatementController extends Controller
{
    public function index(Request $request)
    {
        $transactions=FinanceTransaction::query()->with('user')->when($request->integer('user_id'),fn($q,$id)=>$q->where('user_id',$id))->when($request->filled('q'),fn($q)=>$q->where(fn($q)=>$q->where('reference','like','%'.$request->string('q').'%')->orWhere('note','like','%'.$request->string('q').'%')->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$request->string('q').'%')->orWhere('email','like','%'.$request->string('q').'%'))))->when($request->filled('direction'),fn($q)=>$q->where('direction',$request->input('direction')))->when($request->filled('kind'),fn($q)=>$q->where('kind',$request->input('kind')))->when($request->filled('from'),fn($q)=>$q->whereDate('created_at','>=',$request->input('from')))->when($request->filled('to'),fn($q)=>$q->whereDate('created_at','<=',$request->input('to')))->orderByDesc('id')->paginate(50)->withQueryString();
        $users=User::query()->orderBy('name')->get(['id','name','email']);
        $kinds=FinanceTransaction::query()->select('kind')->distinct()->orderBy('kind')->pluck('kind');
        return view('admin.finances.statements.index',compact('transactions','users','kinds'));
    }

    public function show(Request $request, User $user)
    {
        [$transactions,$summary,$opening] = $this->statement($request,$user,true);
        return view('admin.finances.statements.show', compact('user','transactions','summary','opening'));
    }

    public function print(Request $request, User $user)
    {
        [$transactions,$summary,$opening] = $this->statement($request,$user,false);
        return view('admin.finances.statements.print', compact('user','transactions','summary','opening'));
    }

    public function export(Request $request, User $user)
    {
        [$transactions,,$opening] = $this->statement($request,$user,false);
        return response()->streamDownload(function() use($transactions,$opening): void {
            $out=fopen('php://output','wb'); fputcsv($out,['Date','Reference','Kind','Debit','Credit','Running balance']);
            $balance=$opening;
            foreach($transactions as $row){ $debit=$row->direction==='expense'?$row->amount:''; $credit=$row->direction==='income'?$row->amount:''; $balance=$row->balance_after ?? ($row->direction==='income'?bcadd((string)$balance,(string)$row->amount,4):bcsub((string)$balance,(string)$row->amount,4)); fputcsv($out,[$row->created_at,$row->reference,$row->kind,$debit,$credit,$balance]); }
            fclose($out);
        }, 'statement-'.$user->id.'-'.now()->format('Ymd').'.csv',['Content-Type'=>'text/csv']);
    }

    private function statement(Request $request, User $user, bool $paginate): array
    {
        $data=$request->validate(['from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from']);
        $base=FinanceTransaction::query()->where('user_id',$user->id);
        $opening=(clone $base)->when($data['from']??null,fn($q,$v)=>$q->whereDate('created_at','<',$v))->orderByDesc('id')->value('balance_after') ?? 0;
        $query=$base->when($data['from']??null,fn($q,$v)=>$q->whereDate('created_at','>=',$v))->when($data['to']??null,fn($q,$v)=>$q->whereDate('created_at','<=',$v))->orderBy('id');
        $summary=['income'=>(clone $query)->where('direction','income')->sum('amount'),'expense'=>(clone $query)->where('direction','expense')->sum('amount'),'refund'=>(clone $query)->where('kind','order_release')->sum('amount')];
        return [$paginate ? $query->paginate(50)->withQueryString() : $query->get(),$summary,$opening];
    }
}
