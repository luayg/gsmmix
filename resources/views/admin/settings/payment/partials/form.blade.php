@php
  $prefix = 'gateway-'.($gateway?->id ?? 'new').'-';
  $system = (bool) ($gateway?->is_system ?? false);
  $credentials = $gateway?->credentials ?? [];
@endphp
<div class="row g-3">
  @if(!$system)
    <div class="col-md-4"><label class="form-label">Name</label><input name="name" required maxlength="100" class="form-control" value="{{ old('name',$gateway?->name) }}"></div>
    <div class="col-md-4"><label class="form-label">Slug</label><input name="slug" required maxlength="100" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" class="form-control" value="{{ old('slug',$gateway?->slug) }}" placeholder="bank-transfer"></div>
    <input type="hidden" name="driver" value="manual">
    <div class="col-md-4"><label class="form-label">Logo</label><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" class="form-control"></div>
  @else
    <div class="col-12"><div class="alert alert-info mb-0"><strong>{{ $gateway->name }}</strong> is built in. Its name, driver and order are fixed; only credentials, fees and availability can be changed.</div></div>
  @endif

  <div class="col-md-6"><label class="form-label">Short description</label><textarea name="description" maxlength="2000" rows="2" class="form-control">{{ old('description',$gateway?->description) }}</textarea></div>
  <div class="col-md-6"><label class="form-label">Customer instructions</label><textarea name="instructions" maxlength="10000" rows="2" class="form-control">{{ old('instructions',$gateway?->instructions) }}</textarea></div>

  @if(!$system)
    <div class="col-12"><label class="form-label">Payment / bank details shown to customer</label><textarea name="payment_details" maxlength="5000" rows="4" class="form-control">{{ old('payment_details',data_get($gateway?->config,'payment_details')) }}</textarea></div>
  @elseif($gateway->driver === 'paypal')
    <div class="col-12"><h6>PayPal REST API credentials</h6><p class="small text-muted">Create a REST app in PayPal Developer Dashboard, then add the webhook URL shown below and subscribe to PAYMENT.CAPTURE.COMPLETED.</p></div>
    <div class="col-md-6"><label class="form-label">Client ID</label><input name="client_id" class="form-control" autocomplete="off" placeholder="{{ isset($credentials['client_id']) ? 'Saved — leave blank to keep' : '' }}"></div>
    <div class="col-md-6"><label class="form-label">Client secret</label><input type="password" name="client_secret" class="form-control" autocomplete="new-password" placeholder="{{ isset($credentials['client_secret']) ? 'Saved — leave blank to keep' : '' }}"></div>
    <div class="col-md-6"><label class="form-label">Webhook ID</label><input name="paypal_webhook_id" class="form-control" autocomplete="off" placeholder="{{ isset($credentials['paypal_webhook_id']) ? 'Saved — leave blank to keep' : '' }}"></div>
    <div class="col-md-6"><label class="form-label">Webhook URL</label><input readonly class="form-control" value="{{ url('/payment/webhooks/paypal') }}"></div>
  @elseif($gateway->driver === 'binance_pay')
    <div class="col-12"><h6>Binance Pay Merchant credentials</h6><p class="small text-muted">Use the API identity key and secret generated in Binance Merchant Management. Paste Binance's webhook public key for signature verification.</p></div>
    <div class="col-md-6"><label class="form-label">API identity key</label><input name="binance_api_key" class="form-control" autocomplete="off" placeholder="{{ isset($credentials['binance_api_key']) ? 'Saved — leave blank to keep' : '' }}"></div>
    <div class="col-md-6"><label class="form-label">API secret key</label><input type="password" name="binance_secret_key" class="form-control" autocomplete="new-password" placeholder="{{ isset($credentials['binance_secret_key']) ? 'Saved — leave blank to keep' : '' }}"></div>
    <div class="col-md-6"><label class="form-label">Webhook public key (PEM)</label><textarea name="binance_webhook_public_key" rows="4" class="form-control" autocomplete="off" placeholder="{{ isset($credentials['binance_webhook_public_key']) ? 'Saved — leave blank to keep' : '-----BEGIN PUBLIC KEY-----' }}"></textarea></div>
    <div class="col-md-6"><label class="form-label">Webhook URL</label><input readonly class="form-control" value="{{ url('/payment/webhooks/binance-pay') }}"></div>
  @elseif($gateway->driver === 'usdt')
    <div class="col-12"><div class="alert alert-warning mb-0">Only a receiving address and a read-only blockchain provider key are required. Never enter a wallet seed phrase or private key.</div></div>
    <div class="col-md-4"><label class="form-label">Network</label><select name="usdt_network" class="form-select"><option value="TRC20" @selected(old('usdt_network',data_get($gateway->config,'network'))==='TRC20')>TRC20 (TRON)</option><option value="BEP20" @selected(old('usdt_network',data_get($gateway->config,'network'))==='BEP20')>BEP20 (BNB Smart Chain)</option></select></div>
    <div class="col-md-8"><label class="form-label">Receiving wallet address</label><input name="wallet_address" class="form-control" autocomplete="off" placeholder="{{ isset($credentials['wallet_address']) ? 'Saved — leave blank to keep' : '' }}"></div>
    <div class="col-md-6"><label class="form-label">Blockchain provider URL</label><input type="url" name="provider_url" class="form-control" placeholder="{{ isset($credentials['provider_url']) ? 'Saved — leave blank to keep' : 'TRON API or BSC JSON-RPC URL' }}"></div>
    <div class="col-md-6"><label class="form-label">Provider API key (optional)</label><input type="password" name="provider_api_key" class="form-control" autocomplete="new-password" placeholder="{{ isset($credentials['provider_api_key']) ? 'Saved — leave blank to keep' : '' }}"></div>
    <div class="col-md-8"><label class="form-label">Official USDT token contract address</label><input name="contract_address" class="form-control" placeholder="{{ isset($credentials['contract_address']) ? 'Saved — leave blank to keep' : '' }}"></div>
    <div class="col-md-4"><label class="form-label">Required confirmations</label><input type="number" min="1" max="1000" name="confirmations" class="form-control" value="{{ old('confirmations',data_get($gateway->config,'confirmations',12)) }}"></div>
  @endif

  <div class="col-md-3"><label class="form-label">Fixed fee</label><input name="fixed_fee" required inputmode="decimal" class="form-control" value="{{ old('fixed_fee',$gateway?->fixed_fee ?? 0) }}"></div>
  <div class="col-md-3"><label class="form-label">Percentage fee</label><div class="input-group"><input name="percent_fee" required inputmode="decimal" class="form-control" value="{{ old('percent_fee',$gateway?->percent_fee ?? 0) }}"><span class="input-group-text">%</span></div></div>
  <div class="col-md-3"><label class="form-label">Tax</label><div class="input-group"><input name="tax_percent" inputmode="decimal" class="form-control" value="{{ old('tax_percent',$gateway?->tax_percent ?? 0) }}"><span class="input-group-text">%</span></div></div>
  <div class="col-md-3"><label class="form-label">Minimum / maximum</label><div class="input-group"><input name="minimum_amount" class="form-control" placeholder="Min" value="{{ old('minimum_amount',$gateway?->minimum_amount) }}"><input name="maximum_amount" class="form-control" placeholder="Max" value="{{ old('maximum_amount',$gateway?->maximum_amount) }}"></div></div>
  <div class="col-md-8"><label class="form-label d-block">Supported currencies</label>@foreach($currencies as $currency)<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="currency_ids[]" id="{{ $prefix }}currency{{ $currency->id }}" value="{{ $currency->id }}" @checked(in_array($currency->id,old('currency_ids',!$gateway ? ($currency->is_default ? [$currency->id] : []) : $gateway->currencies->modelKeys())))><label class="form-check-label" for="{{ $prefix }}currency{{ $currency->id }}">{{ $currency->code }}</label></div>@endforeach</div>
  @if(!$system)<div class="col-md-2"><label class="form-label">Ordering</label><input type="number" min="0" name="ordering" class="form-control" value="{{ old('ordering',$gateway?->ordering ?? 0) }}"></div>@endif
  <div class="col-md-2"><input type="hidden" name="active" value="0"><div class="form-check form-switch mt-4"><input class="form-check-input" id="{{ $prefix }}active" type="checkbox" name="active" value="1" @checked(old('active',$gateway?->active ?? false))><label class="form-check-label" for="{{ $prefix }}active">Active</label></div></div>
  @if($system && $gateway->driver === 'paypal')<div class="col-md-2"><input type="hidden" name="sandbox" value="0"><div class="form-check form-switch mt-4"><input class="form-check-input" id="{{ $prefix }}sandbox" type="checkbox" name="sandbox" value="1" @checked(old('sandbox',$gateway?->sandbox ?? true))><label class="form-check-label" for="{{ $prefix }}sandbox">Sandbox</label></div></div>@else<input type="hidden" name="sandbox" value="0">@endif
</div>
