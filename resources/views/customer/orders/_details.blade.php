<div class="order-modal-head"><div><span class="eyebrow">{{ strtoupper($order['type']) }} ORDER</span><h2 class="h4 fw-bold mb-1">Order #{{ $order['id'] }}</h2><p class="text-muted mb-0">{{ $order['created_at']?->format('F j, Y · H:i') }}</p></div><span class="status-action status-{{ str($order['status'])->lower()->replace(' ','-') }}">{{ ucfirst($order['status']) }}</span></div>
<div class="order-modal-service"><div class="service-icon"><i class="fas fa-{{ ['imei'=>'mobile-screen','server'=>'server','file'=>'file-lines','smm'=>'hashtag','product'=>'box'][$order['type']]??'box' }}"></i></div><div><small>SERVICE</small><strong>{{ $order['service'] }}</strong></div></div>
<div class="detail-grid"><div><span>Device / target</span><strong>{{ $order['device'] }}</strong></div>@if($order['quantity'])<div><span>Quantity</span><strong>{{ $order['quantity'] }}</strong></div>@endif<div><span>Amount charged</span><strong>${{ number_format((float)$order['amount'],2) }}</strong></div></div>
@php
  $result=$order['result']; $isSuccess=in_array(strtolower($order['status']),['success','completed','done']);
  $tone=function(string $label,string $value):string{$label=strtolower($label);$value=strtolower(trim($value));if(in_array($value,['on','lost','expired','blocked','blacklisted','locked','no'],true))return 'danger';if(in_array($value,['off','clean','active','eligible','supported','unlocked','yes','complete','completed','success'],true))return 'success';if(str_contains($label,'lost')||str_contains($label,'icloud')||str_contains($label,'sim-lock')){if(str_contains($value,'clean')||str_contains($value,'unlock')||$value==='off')return 'success';if(str_contains($value,'lost')||str_contains($value,'lock')||$value==='on')return 'danger';}return '';};
@endphp
<div class="customer-result mt-4"><span class="customer-result-title">RESULT</span>
@if($result['image']||$result['items']||$result['text'])
  @if($result['image'])<div class="customer-result-image"><img src="{{ $result['image'] }}" alt="Order result image"></div>@endif
  @if($result['items'])<div class="customer-result-table">@foreach($result['items'] as $item)@php($badge=$tone($item['label'],$item['value']))<div class="customer-result-row"><span>{{ $item['label'] }}</span><strong @class(['result-badge'=>(bool)$badge,'result-badge-'.$badge=>(bool)$badge])>{{ $item['value'] }}</strong></div>@endforeach</div>
  @elseif($result['text'])<pre class="customer-result-text">{{ $result['text'] }}</pre>@endif
@elseif($isSuccess)<div class="customer-result-empty"><span class="result-badge result-badge-success">Success</span></div>
@else<p class="mb-0 text-muted">The result is not available yet.</p>@endif
</div>
@if($model->comments)<div class="modal-note"><span>COMMENTS</span><p>{{ $model->comments }}</p></div>@endif
