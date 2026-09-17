@extends('layouts.admin')
@section('title','Live Operations Dashboard')
@section('bodyClass','has-command-dashboard')
@section('content')
<div class="command-dashboard" data-refresh-seconds="60">
  <header class="command-head">
    <div><span class="command-eyebrow">REAL-TIME BUSINESS INTELLIGENCE</span><h1>Welcome back, {{ str(auth()->user()->name ?? 'Admin')->before(' ') }}</h1><p>Live insights from your GSM MIX platform</p></div>
    <div class="command-head-meta"><span class="live-refresh"><i></i> LIVE · <b id="refreshCountdown">60s</b><small>Auto-refreshing</small></span><span class="command-quote">“Smarter Tools. Stronger Connections.”<small>— GSM MIX</small></span><time><i class="far fa-calendar"></i><span>{{ now()->format('D, M d, Y') }}<small>{{ now()->format('H:i') }} ({{ config('app.timezone') }})</small></span></time></div>
  </header>

  <section class="command-kpis">
    @foreach([
      ['Today Orders',$todayOrders,'fa-cart-shopping','cyan'],['Today Registrations',$todayRegistrations,'fa-user-plus','blue'],['Today Payments','$'.number_format($todayPayments,2),'fa-credit-card','violet'],['Today Profit','$'.number_format($todayProfit,2),'fa-chart-column','green'],['Online Users',$onlineUsers,'fa-users','blue']
    ] as [$label,$value,$icon,$tone])
      <article class="command-card kpi-card tone-{{ $tone }}"><span class="kpi-icon"><i class="fas {{ $icon }}"></i></span><div><span>{{ $label }}</span><strong>{{ $value }}</strong><small><i class="fas fa-arrow-trend-up"></i> Live total</small></div><svg class="spark" viewBox="0 0 80 28" aria-hidden="true"><polyline points="0,24 10,18 20,21 31,11 41,15 52,7 64,12 80,3"/></svg></article>
    @endforeach
  </section>

  <section class="command-grid command-performance">
    <article class="command-card profit-panel">
      <div class="panel-head"><div><h2><i class="fas fa-chart-simple"></i> Monthly Profit</h2><strong>${{ number_format($monthProfit,2) }}</strong><span class="trend {{ ($profitChange ?? 0)>=0?'up':'down' }}">{{ ($profitChange ?? 0)>=0?'↑':'↓' }} {{ $profitChange===null?'—':abs($profitChange).'%' }} <small>vs last month</small></span></div><span class="year-chip">{{ now()->year }} <i class="fas fa-chevron-down"></i></span></div>
      <div class="profit-chart"><svg id="profitChart" viewBox="0 0 760 210" preserveAspectRatio="none" role="img" aria-label="Monthly profit chart"><defs><linearGradient id="profitFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2786ff" stop-opacity=".56"/><stop offset="1" stop-color="#2786ff" stop-opacity="0"/></linearGradient></defs><g class="chart-grid"></g><path class="chart-area"></path><polyline class="chart-line"></polyline><g class="chart-labels"></g></svg></div>
    </article>
    @foreach([['Order Acceptance Rate',$acceptanceRate,'accepted',$statuses['success'],'green'],['Order Rejection Rate',$rejectionRate,'rejected',$statuses['rejected']+$statuses['cancelled'],'red']] as [$label,$rate,$status,$count,$tone])
      <article class="command-card rate-card"><h2><i class="fas {{ $tone==='green'?'fa-user-check':'fa-circle-xmark' }}"></i> {{ $label }}</h2><div class="rate-body"><div class="ring tone-{{ $tone }}" style="--rate:{{ min(100,$rate) }}"><span>{{ $rate }}%</span></div><div class="rate-legend"><span><i class="dot {{ $tone }}"></i>{{ ucfirst($status) }}<strong>{{ number_format($count) }}</strong></span><span><i class="dot muted"></i>Total<strong>{{ number_format(array_sum($statuses)) }}</strong></span></div></div></article>
    @endforeach
  </section>

  <section class="command-grid command-live-row">
    <article class="command-card compact-panel"><div class="panel-title"><h2><i class="fas fa-clock text-warning"></i> Pending Approval <b>{{ $pending->count() }}</b></h2><a href="{{ route('admin.orders.imei.index') }}">View all</a></div><div class="command-list">@forelse($pending as $order)<div><strong>#ORD-{{ $order->id }}</strong><span>{{ strtoupper($order->type) }} · {{ $order->customer }}</span><time>{{ \Illuminate\Support\Carbon::parse($order->created_at)->diffForHumans(short:true) }}</time><a class="mini-action" href="{{ route('admin.orders.'.$order->type.'.index') }}">Review</a></div>@empty<p class="empty-state">No orders are waiting for approval.</p>@endforelse</div></article>
    <article class="command-card compact-panel"><div class="panel-title"><h2><i class="fas fa-triangle-exclamation text-danger"></i> Payment Review Alerts <b>{{ $reviewPayments }}</b></h2><a href="{{ route('admin.finances.payment-reviews.index') }}">View all</a></div><div class="command-list">@forelse($paymentAlerts as $payment)<div><strong>#PAY-{{ $payment->id }}</strong><span>${{ number_format($payment->amount_base,2) }} · User #{{ $payment->user_id }}</span><time>{{ \Illuminate\Support\Carbon::parse($payment->created_at)->diffForHumans(short:true) }}</time><a class="mini-action" href="{{ route('admin.finances.payment-reviews.index') }}">Review</a></div>@empty<p class="empty-state">No payments require review.</p>@endforelse</div></article>
    <article class="command-card online-panel"><div class="panel-title"><h2><i class="fas fa-users"></i> Online Users</h2><span class="online-now"><i></i> Live</span></div><div class="connection-map" aria-hidden="true"><svg viewBox="0 0 420 150"><path d="M25 52l31-22 53 8 19 22-24 15-31-8-22 14-18-10zm116 9 22-19 37 3 15 15-12 15 18 18-14 42-21-10-7-33-31-12zm91-23 40-21 76 14 22 21-27 11-17-9-18 11-25-4-15 18-25-8zm75 57 38 8 24 25-21 11-32-15z"/></svg><span class="map-pulse p1"></span><span class="map-pulse p2"></span><span class="map-pulse p3"></span></div><div class="session-list">@forelse($online->take(4) as $session)<span><i></i>User #{{ $session->user_id }}<code>{{ $session->ip_address }}</code></span>@empty<span class="empty-state">No active database sessions.</span>@endforelse</div></article>
  </section>

  <section class="command-grid command-bottom">
    <article class="command-card recent-panel"><div class="panel-title"><h2><i class="fas fa-receipt"></i> Recent Orders</h2><span>{{ number_format($monthOrders) }} this month</span></div><div class="table-responsive"><table class="command-table"><thead><tr><th>Order</th><th>Customer</th><th>Service</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>@forelse($recentOrders as $order)<tr><td><b>#ORD-{{ $order->id }}</b></td><td>{{ $order->customer }}</td><td>{{ strtoupper($order->type) }}</td><td>${{ number_format($order->amount,2) }}</td><td><span class="status-pill is-{{ $order->status }}">{{ ucfirst($order->status) }}</span></td><td>{{ \Illuminate\Support\Carbon::parse($order->created_at)->format('M d, H:i') }}</td></tr>@empty<tr><td colspan="6" class="empty-state">No recent orders.</td></tr>@endforelse</tbody></table></div></article>
    <article class="command-card activity-panel"><div class="panel-title"><h2><i class="fas fa-wave-square"></i> Live Activity</h2><a href="{{ route('admin.logs.activity') }}">View all</a></div><div class="activity-line">@foreach($recentOrders->take(3) as $order)<div><time>{{ \Illuminate\Support\Carbon::parse($order->created_at)->format('H:i') }}</time><i class="fas fa-cart-shopping"></i><span>Order <b>#ORD-{{ $order->id }}</b> {{ $order->status }}</span></div>@endforeach @foreach($recentUsers->take(3) as $user)<div><time>{{ $user->created_at->format('H:i') }}</time><i class="fas fa-user-plus"></i><span>New registration <b>{{ $user->name }}</b></span></div>@endforeach @if($recentOrders->isEmpty() && $recentUsers->isEmpty())<p class="empty-state">No recent activity.</p>@endif</div></article>
  </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const root=document.querySelector('.command-dashboard'); if(!root)return;
  let seconds=Number(root.dataset.refreshSeconds||60), remaining=seconds, label=document.getElementById('refreshCountdown');
  window.setInterval(()=>{remaining-=1;if(label)label.textContent=remaining+'s';if(remaining<=0)window.location.reload();},1000);
  const values=@json($profitValues), labels=@json($profitLabels), svg=document.getElementById('profitChart'); if(!svg)return;
  const W=760,H=210,pad=24,max=Math.max(...values,1),points=values.map((v,i)=>[pad+i*((W-pad*2)/Math.max(values.length-1,1)),H-pad-(v/max)*(H-pad*2)]);
  const line=points.map(p=>p.join(',')).join(' '),area=`M ${pad} ${H-pad} L ${line.replaceAll(' ',' L ')} L ${W-pad} ${H-pad} Z`;
  svg.querySelector('.chart-line').setAttribute('points',line);svg.querySelector('.chart-area').setAttribute('d',area);
  svg.querySelector('.chart-grid').innerHTML=[0,1,2,3,4].map(i=>`<line x1="${pad}" y1="${pad+i*(H-pad*2)/4}" x2="${W-pad}" y2="${pad+i*(H-pad*2)/4}"/>`).join('');
  svg.querySelector('.chart-labels').innerHTML=labels.map((l,i)=>`<text x="${points[i][0]}" y="${H-3}" text-anchor="middle">${l}</text>`).join('');
});
</script>
@endpush
