<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class RejectBlockedIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip=(string)$request->ip();
        if ($ip && Schema::hasTable('blocked_ips')) {
            $blocked=DB::table('blocked_ips')->where('ip_address',$ip)->where('active',true)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->exists();
            abort_if($blocked,403,'Access from this IP address has been blocked.');
        }
        return $next($request);
    }
}
