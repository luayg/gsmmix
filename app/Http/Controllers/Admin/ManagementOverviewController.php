<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagementOverviewController extends Controller
{
    public function finances()
    {
        $title = 'Finance overview';
        $description = 'Current account balances and recorded credit adjustments. Order charges and refunds remain recorded with their orders.';
        $columns = ['Metric', 'Value'];
        $rows = [
            ['Account balances (credits)', number_format((float) DB::table('users')->sum('balance'), 4, '.', '')],
            ['Finance accounts', FinanceAccount::count()],
            ['Recorded account transactions', FinanceTransaction::count()],
        ];
        return view('admin.management.table', compact('title', 'description', 'columns', 'rows'));
    }

    public function statements(Request $request)
    {
        $data = $request->validate(['user_id' => 'nullable|integer|min:1']);
        $pagination = FinanceAccount::query()->with('user')->orderBy('user_id')
            ->when($data['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->paginate(25)->withQueryString();
        $rows = $pagination->getCollection()->map(fn ($account) => [
            $account->user_id, $account->user?->name ?? 'Historical account',
            number_format((float) ($account->user?->balance ?? 0), 4, '.', ''),
            number_format((float) $account->total_receipts, 2, '.', ''),
            number_format((float) $account->paid_credits, 2, '.', ''),
            number_format(max(0, (float) $account->total_receipts - (float) $account->paid_credits), 2, '.', ''),
            number_format((float) $account->overdraft_limit, 2, '.', ''),
        ]);
        $title = 'Account statements';
        $description = 'Balances are read from the live account. Receipt totals do not replace spendable credits.';
        $columns = ['Customer ID', 'Customer', 'Balance', 'Credit receipts', 'Paid credits', 'Unpaid credits', 'Overdraft'];
        return view('admin.management.table', compact('title', 'description', 'columns', 'rows', 'pagination'));
    }

    public function transactions(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable|integer|min:1',
            'direction' => 'nullable|in:income,expense',
        ]);
        $pagination = FinanceTransaction::query()->with('user')->orderByDesc('id')
            ->when($data['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($data['direction'] ?? null, fn ($query, $direction) => $query->where('direction', $direction))
            ->paginate(25)->withQueryString();
        $rows = $pagination->getCollection()->map(fn ($row) => [
            $row->id, $row->user?->name ?? 'Historical account', $row->kind, $row->direction,
            number_format((float) $row->amount, 2, '.', ''), $row->balance_after, $row->reference, $row->created_at,
        ]);
        $title = 'Account transactions';
        $description = 'Recorded payments, credit adjustments and account movements. Order billing details are available on the corresponding orders.';
        $columns = ['ID', 'Customer', 'Kind', 'Direction', 'Credits', 'Balance after', 'Reference', 'Date'];
        return view('admin.management.table', compact('title', 'description', 'columns', 'rows', 'pagination'));
    }

    public function users()
    {
        $title = 'User reports';
        $description = 'Account counts grouped by account status.';
        $columns = ['Status', 'Accounts'];
        $rows = DB::table('users')->select('status')->selectRaw('COUNT(*) as accounts')
            ->groupBy('status')->orderBy('status')->get()->map(fn ($row) => [
                $row->status, $row->accounts,
            ]);
        return view('admin.management.table', compact('title', 'description', 'columns', 'rows'));
    }

    public function services()
    {
        $title = 'Service report';
        $description = 'Current catalog availability for each service kind.';
        $columns = ['Kind', 'Total services', 'Active', 'Inactive'];
        $rows = [];
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $query = DB::table($kind . '_services');
            $total = (clone $query)->count();
            $active = (clone $query)->where('active', 1)->count();
            $rows[] = [strtoupper($kind), $total, $active, $total - $active];
        }
        return view('admin.management.table', compact('title', 'description', 'columns', 'rows'));
    }

    public function products()
    {
        $title = 'Product report';
        $description = 'Product order counts and delivered credit totals. Historical orders without a product link are excluded.';
        $columns = ['Product', 'Active', 'Catalog credits', 'Orders', 'Waiting orders', 'Delivered credits'];
        $pagination = Product::query()->withCount('orders')
            ->withCount(['orders as waiting_orders' => fn ($query) => $query->whereIn('status', ['waiting', 'inprogress'])])
            ->withSum(['orders as delivered_credits' => fn ($query) => $query->where('status', 'success')], 'order_price')
            ->orderBy('id')->paginate(25);
        $rows = $pagination->getCollection()->map(fn ($product) => [
            $product->name, $product->active ? 'Yes' : 'No', number_format($product->price, 2, '.', ''),
            $product->orders_count, $product->waiting_orders, number_format((float) $product->delivered_credits, 2, '.', ''),
        ]);
        return view('admin.management.table', compact('title', 'description', 'columns', 'rows', 'pagination'));
    }

    public function export(string $type)
    {
        abort_unless(in_array($type, ['users','services','products'], true), 404);
        return response()->streamDownload(function () use ($type): void {
            $out=fopen('php://output','wb');
            if($type==='users'){fputcsv($out,['status','accounts']);foreach(DB::table('users')->select('status')->selectRaw('COUNT(*) accounts')->groupBy('status')->orderBy('status')->get() as $r)fputcsv($out,[$r->status,$r->accounts]);}
            elseif($type==='services'){fputcsv($out,['kind','total','active','inactive']);foreach(['imei','server','file','smm'] as $kind){$q=DB::table($kind.'_services');$total=(clone $q)->count();$active=(clone $q)->where('active',1)->count();fputcsv($out,[strtoupper($kind),$total,$active,$total-$active]);}}
            else{fputcsv($out,['product','active','catalog_price','orders','successful_sales']);foreach(Product::query()->withCount('orders')->withSum(['orders as sales'=>fn($q)=>$q->where('status','success')],'order_price')->orderBy('id')->cursor() as $p)fputcsv($out,[$p->name,$p->active?1:0,$p->price,$p->orders_count,$p->sales??0]);}
            fclose($out);
        },$type.'-report-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv']);
    }

}
