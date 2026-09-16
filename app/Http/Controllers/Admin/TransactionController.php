<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceTransaction;
use Illuminate\Http\Request;

final class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = $this->query($request)->paginate(30)->withQueryString();
        return view('admin.finances.transactions.index', compact('transactions'));
    }

    public function show(FinanceTransaction $transaction)
    {
        $transaction->load('user', 'source');
        return view('admin.finances.transactions.show', compact('transaction'));
    }

    public function export(Request $request)
    {
        $rows = $this->query($request)->cursor();
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['ID','Customer ID','Kind','Direction','Paid','Amount','Currency','Original amount','Rate','Balance before','Balance after','Reference','Created']);
            foreach ($rows as $row) fputcsv($out, [$row->id,$row->user_id,$row->kind,$row->direction,$row->paid ? 1 : 0,$row->amount,$row->currency_code,$row->original_amount,$row->exchange_rate,$row->balance_before,$row->balance_after,$row->reference,$row->created_at?->toIso8601String()]);
            fclose($out);
        }, 'transactions-'.now()->format('Ymd-His').'.csv', ['Content-Type'=>'text/csv']);
    }

    private function query(Request $request)
    {
        $data = $request->validate(['q'=>'nullable|string|max:100','user_id'=>'nullable|integer|min:1','direction'=>'nullable|in:income,expense','kind'=>'nullable|string|max:40','paid'=>'nullable|in:0,1','from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from']);
        return FinanceTransaction::query()->with('user')->orderByDesc('id')
            ->when($data['q'] ?? null, fn($q,$value)=>$q->where(fn($q)=>$q->where('reference','like','%'.$value.'%')->orWhere('note','like','%'.$value.'%')->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$value.'%')->orWhere('email','like','%'.$value.'%'))))
            ->when($data['user_id'] ?? null, fn($q,$v)=>$q->where('user_id',$v))
            ->when($data['direction'] ?? null, fn($q,$v)=>$q->where('direction',$v))
            ->when($data['kind'] ?? null, fn($q,$v)=>$q->where('kind',$v))
            ->when(array_key_exists('paid',$data), fn($q)=>$q->where('paid',(bool)$data['paid']))
            ->when($data['from'] ?? null, fn($q,$v)=>$q->whereDate('created_at','>=',$v))
            ->when($data['to'] ?? null, fn($q,$v)=>$q->whereDate('created_at','<=',$v));
    }
}
