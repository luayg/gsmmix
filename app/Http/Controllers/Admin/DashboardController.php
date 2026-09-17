<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DashboardController extends Controller
{
    private const ORDERS = ['imei' => 'imei_orders', 'server' => 'server_orders', 'file' => 'file_orders', 'smm' => 'smm_orders', 'product' => 'product_orders'];

    public function __invoke()
    {
        $today = now()->startOfDay();
        $month = now()->startOfMonth();
        $previousMonth = now()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $month->copy();
        $statuses = ['waiting'=>0,'inprogress'=>0,'success'=>0,'rejected'=>0,'cancelled'=>0];
        $todayOrders = 0;
        $monthOrders = 0;
        $monthProfit = 0.0;
        $previousProfit = 0.0;
        $pending = collect();

        foreach (self::ORDERS as $type => $table) {
            if (!Schema::hasTable($table)) continue;
            $todayOrders += DB::table($table)->where('created_at', '>=', $today)->count();
            $monthOrders += DB::table($table)->where('created_at', '>=', $month)->count();
            foreach (array_keys($statuses) as $status) $statuses[$status] += DB::table($table)->where('status', $status)->count();
            if (Schema::hasColumn($table, 'profit')) {
                $monthProfit += (float) DB::table($table)->where('created_at', '>=', $month)->sum('profit');
                $previousProfit += (float) DB::table($table)->whereBetween('created_at', [$previousMonth, $previousMonthEnd])->sum('profit');
            }
            $pending = $pending->concat(DB::table($table)->whereIn('status', ['waiting','inprogress'])->latest('id')->limit(5)->get(['id','user_id','status','created_at'])->map(function ($row) use ($type) {$row->type=$type;return $row;}));
        }

        $decided = $statuses['success'] + $statuses['rejected'] + $statuses['cancelled'];
        $acceptanceRate = $decided ? round($statuses['success'] / $decided * 100, 1) : 0;
        $rejectionRate = $decided ? round(($statuses['rejected'] + $statuses['cancelled']) / $decided * 100, 1) : 0;
        $profitChange = $previousProfit != 0.0 ? round(($monthProfit - $previousProfit) / abs($previousProfit) * 100, 1) : null;
        $onlineUsers = Schema::hasTable('sessions') && Schema::hasColumn('sessions','user_id')
            ? DB::table('sessions')->whereNotNull('user_id')->where('last_activity','>=',now()->subMinutes(5)->timestamp)->distinct()->count('user_id') : 0;
        $online = Schema::hasTable('sessions') && Schema::hasColumn('sessions','ip_address')
            ? DB::table('sessions')->whereNotNull('user_id')->where('last_activity','>=',now()->subMinutes(5)->timestamp)->latest('last_activity')->limit(8)->get(['user_id','ip_address','last_activity']) : collect();
        $todayPayments = Schema::hasTable('payment_transactions') ? (float) DB::table('payment_transactions')->where('status','paid')->where('paid_at','>=',$today)->sum('amount_base') : 0;
        $reviewPayments = Schema::hasTable('payment_transactions') ? DB::table('payment_transactions')->where('status','review')->count() : 0;

        return view('admin.dashboard', [
            'todayOrders'=>$todayOrders,'monthOrders'=>$monthOrders,'todayRegistrations'=>Schema::hasTable('users')?User::where('created_at','>=',$today)->count():0,
            'todayPayments'=>$todayPayments,'monthProfit'=>$monthProfit,'profitChange'=>$profitChange,'acceptanceRate'=>$acceptanceRate,'rejectionRate'=>$rejectionRate,
            'statuses'=>$statuses,'pending'=>$pending->sortByDesc('created_at')->take(10),'reviewPayments'=>$reviewPayments,'onlineUsers'=>$onlineUsers,'online'=>$online,
            'totalUsers'=>Schema::hasTable('users')?User::count():0,
        ]);
    }
}
