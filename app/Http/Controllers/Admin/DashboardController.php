<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DashboardController extends Controller
{
    private const ORDERS = ['imei' => 'imei_orders', 'server' => 'server_orders', 'file' => 'file_orders', 'smm' => 'smm_orders', 'product' => 'product_orders'];

    public function __invoke()
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $month = $now->copy()->startOfMonth();
        $previousMonth = $month->copy()->subMonth();
        $statuses = ['waiting' => 0, 'inprogress' => 0, 'success' => 0, 'rejected' => 0, 'cancelled' => 0];
        $todayOrders = $monthOrders = 0;
        $todayProfit = $monthProfit = $previousProfit = 0.0;
        $pending = collect();
        $recentOrders = collect();
        $profitSeries = collect(range(11, 0))->mapWithKeys(fn ($offset) => [$month->copy()->subMonths($offset)->format('Y-m') => 0.0])->put($month->format('Y-m'), 0.0);

        foreach (self::ORDERS as $type => $table) {
            if (! Schema::hasTable($table)) continue;
            $query = DB::table($table);
            $todayOrders += (clone $query)->where('created_at', '>=', $today)->count();
            $monthOrders += (clone $query)->where('created_at', '>=', $month)->count();
            foreach (array_keys($statuses) as $status) $statuses[$status] += (clone $query)->where('status', $status)->count();

            if (Schema::hasColumn($table, 'profit')) {
                $todayProfit += (float) (clone $query)->where('created_at', '>=', $today)->sum('profit');
                $monthProfit += (float) (clone $query)->where('created_at', '>=', $month)->sum('profit');
                $previousProfit += (float) (clone $query)->whereBetween('created_at', [$previousMonth, $month])->sum('profit');
                $periodSql = DB::connection()->getDriverName() === 'sqlite'
                    ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";
                $monthly = (clone $query)->where('created_at', '>=', $month->copy()->subMonths(11))
                    ->selectRaw("{$periodSql} as period, SUM(profit) as total")->groupBy('period')->pluck('total', 'period');
                foreach ($monthly as $period => $total) if ($profitSeries->has($period)) $profitSeries[$period] += (float) $total;
            }

            $columns = array_values(array_filter(['id', 'user_id', 'status', 'created_at', Schema::hasColumn($table, 'order_price') ? 'order_price' : null]));
            $decorate = fn ($row) => tap($row, function ($row) use ($type) { $row->type = $type; $row->amount = (float) ($row->order_price ?? 0); $row->admin_url = route("admin.orders.{$type}.index"); });
            $pending = $pending->concat((clone $query)->whereIn('status', ['waiting', 'inprogress'])->latest('id')->limit(6)->get($columns)->map($decorate));
            $recentOrders = $recentOrders->concat((clone $query)->latest('id')->limit(6)->get($columns)->map($decorate));
        }

        $allOrders = $pending->concat($recentOrders);
        $userIds = $allOrders->pluck('user_id')->filter()->unique();
        $userNames = Schema::hasTable('users') ? User::whereIn('id', $userIds)->pluck('name', 'id') : collect();
        foreach ($allOrders as $order) $order->customer = $userNames[$order->user_id] ?? ($order->user_id ? 'User #'.$order->user_id : 'Guest');

        $decided = $statuses['success'] + $statuses['rejected'] + $statuses['cancelled'];
        $acceptanceRate = $decided ? round($statuses['success'] / $decided * 100, 1) : 0;
        $rejectionRate = $decided ? round(($statuses['rejected'] + $statuses['cancelled']) / $decided * 100, 1) : 0;
        $profitChange = $previousProfit != 0.0 ? round(($monthProfit - $previousProfit) / abs($previousProfit) * 100, 1) : null;
        $hasSessions = Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id');
        $onlineQuery = $hasSessions ? DB::table('sessions')->whereNotNull('user_id')->where('last_activity', '>=', $now->copy()->subMinutes(5)->timestamp) : null;
        $onlineUsers = $onlineQuery ? (clone $onlineQuery)->distinct()->count('user_id') : 0;
        $online = $onlineQuery && Schema::hasColumn('sessions', 'ip_address') ? (clone $onlineQuery)->latest('last_activity')->limit(8)->get(['user_id', 'ip_address', 'last_activity']) : collect();
        $todayPayments = Schema::hasTable('payment_transactions') ? (float) DB::table('payment_transactions')->where('status', 'paid')->where('paid_at', '>=', $today)->sum('amount_base') : 0;
        $reviewPayments = Schema::hasTable('payment_transactions') ? DB::table('payment_transactions')->where('status', 'review')->count() : 0;
        $paymentAlerts = Schema::hasTable('payment_transactions') ? DB::table('payment_transactions')->whereIn('status', ['review', 'failed'])->latest('id')->limit(5)->get(['id', 'user_id', 'amount_base', 'status', 'created_at']) : collect();

        return view('admin.dashboard', [
            'todayOrders' => $todayOrders, 'monthOrders' => $monthOrders, 'todayRegistrations' => Schema::hasTable('users') ? User::where('created_at', '>=', $today)->count() : 0,
            'todayPayments' => $todayPayments, 'todayProfit' => $todayProfit, 'monthProfit' => $monthProfit, 'profitChange' => $profitChange,
            'acceptanceRate' => $acceptanceRate, 'rejectionRate' => $rejectionRate, 'statuses' => $statuses,
            'pending' => $pending->sortByDesc('created_at')->take(5), 'recentOrders' => $recentOrders->sortByDesc('created_at')->take(5),
            'reviewPayments' => $reviewPayments, 'paymentAlerts' => $paymentAlerts, 'onlineUsers' => $onlineUsers, 'online' => $online,
            'recentUsers' => Schema::hasTable('users') ? User::latest('id')->limit(5)->get(['id', 'name', 'created_at']) : collect(),
            'totalUsers' => Schema::hasTable('users') ? User::count() : 0,
            'profitLabels' => $profitSeries->keys()->map(fn ($date) => Carbon::createFromFormat('Y-m', $date)->format('M')),
            'profitValues' => $profitSeries->values()->map(fn ($value) => round($value, 2)),
        ]);
    }
}
