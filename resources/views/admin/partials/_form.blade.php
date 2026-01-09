{{-- resources/views/admin/services/partials/_form.blade.php --}}
@php
  /** @var \Illuminate\Database\Eloquent\Model|null $record */
  $isEdit = isset($record) && $record?->id;
  $m = $record ?? null;

  // helper = old()->model()->default
  $v = function ($key, $default = null) use ($m) {
    return old($key, $m?->$key ?? $default);
  };

  // استخدم PUT في حالة التعديل
  $httpMethod = strtoupper($method ?? 'POST');
@endphp

<form id="serviceCreateForm"
      action="{{ $action }}"
      method="POST"
      data-ajax="1">
  @csrf
  @if($httpMethod !== 'POST')
    @method($httpMethod)
  @endif

  <div class="row g-3">
    {{-- ========== اليسار: بيانات أساسية / التسعير ========== --}}
    <div class="col-xl-7">
      <div class="row g-3">

        {{-- Basic --}}
        <div class="col-12">
          <label class="form-label mb-1">Name</label>
          <input name="name" type="text" class="form-control" value="{{ $v('name') }}" required>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Alias</label>
          <input name="alias" type="text" class="form-control" value="{{ $v('alias') }}" placeholder="lowercase-and-dashes">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Icon (URL)</label>
          <input name="icon" type="text" class="form-control" value="{{ $v('icon') }}" placeholder="https://...png">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Delivery time</label>
          <input name="time" type="text" class="form-control" value="{{ $v('time') }}" placeholder="e.g. 1-24h">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Group</label>
          <select name="group_id" class="form-select">
            <option value="">Group</option>
            @foreach(($groups ?? []) as $g)
              <option value="{{ $g->id }}" @selected($v('group_id')==$g->id)>{{ $g->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Type</label>
          {{-- جدولك يعرض default = server --}}
          <select name="type" class="form-select">
            @php $types = ['server'=>'Server','imei'=>'IMEI','file'=>'File','direct'=>'Direct']; @endphp
            @foreach($types as $k=>$label)
              <option value="{{ $k }}" @selected($v('type','server')===$k)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Ordering</label>
          <input name="ordering" type="number" class="form-control" value="{{ $v('ordering',1) }}">
        </div>

        {{-- Pricing --}}
        <div class="col-md-6">
          <label class="form-label mb-1">Cost</label>
          <div class="input-group">
            <input name="cost" type="number" step="0.01" class="form-control" value="{{ $v('cost',0) }}">
            <span class="input-group-text">Credits</span>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Profit</label>
          <div class="input-group">
            <input name="profit" type="number" step="0.01" class="form-control" value="{{ $v('profit',0) }}">
            <span class="input-group-text">Credits</span>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Profit type</label>
          <select name="profit_type" class="form-select">
            {{-- 1=Fixed, 2=Percent (افتراض شائع) --}}
            <option value="1" @selected($v('profit_type',1)==1)>Fixed</option>
            <option value="2" @selected($v('profit_type',1)==2)>Percent</option>
          </select>
          <small class="text-muted">Price will be calculated accordingly.</small>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Preview Price</label>
          <div class="input-group">
            @php
              $cost = floatval($v('cost',0)); $profit = floatval($v('profit',0));
              $pType = intval($v('profit_type',1));
              $calc = $pType===2 ? $cost + (($cost*$profit)/100) : $cost + $profit;
            @endphp
            <input type="text" class="form-control" value="{{ number_format($calc,2) }}" disabled>
            <span class="input-group-text">Credits</span>
          </div>
        </div>

        {{-- Toggles --}}
        @php
          $bools = [
            'active'                  => 'Active',
            'allow_bulk'              => 'Allow bulk orders',
            'allow_duplicates'        => 'Allow duplicates',
            'reply_with_latest'       => 'Reply with latest success result if possible',
            'allow_report'            => 'Allow report',
            'allow_cancel'            => 'Allow cancel (waiting action orders)',
            'use_remote_cost'         => 'Use remote cost',
            'use_remote_price'        => 'Use remote price',
            'stop_on_api_change'      => 'Stop on API change',
            'needs_approval'          => 'Needs approval',
            'device_based'            => 'Device based',
            'reject_on_missing_reply' => 'Reject on missing reply',
          ];
        @endphp

        <div class="col-12">
          @foreach($bools as $name=>$label)
            <input type="hidden" name="{{ $name }}" value="0">
            <div class="form-check form-switch mb-1">
              <input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" id="sw_{{ $name }}" @checked($v($name, in_array($name,['active','allow_bulk','device_based'])?1:0)) >
              <label class="form-check-label" for="sw_{{ $name }}">{{ $label }}</label>
            </div>
          @endforeach
        </div>

        {{-- Timeouts / Expiration --}}
        <div class="col-md-6">
          <label class="form-label mb-1">Allow report time (min)</label>
          <input name="allow_report_time" type="number" class="form-control" value="{{ $v('allow_report_time',0) }}">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Allow cancel time (min)</label>
          <input name="allow_cancel_time" type="number" class="form-control" value="{{ $v('allow_cancel_time',0) }}">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Reply expiration (min)</label>
          <input name="reply_expiration" type="number" class="form-control" value="{{ $v('reply_expiration',0) }}">
        </div>

        <div class="col-12">
          <label class="form-label mb-1">Expiration text</label>
          <textarea name="expiration_text" class="form-control" rows="2">{{ $v('expiration_text') }}</textarea>
        </div>

        {{-- Connectivity / Source --}}
        <div class="col-md-6">
          <label class="form-label mb-1">Source</label>
          {{-- جدولك: source = INT (سنضع mapping افتراضي قابل للتعديل) --}}
          @php
            // غيّر الأرقام لتطابق نظامك إذا اختلف
            $srcMap = [1=>'Manual', 2=>'API', 3=>'Supplier', 4=>'Local'];
          @endphp
          <select name="source" class="form-select">
            <option value="">— Select —</option>
            @foreach($srcMap as $num=>$label)
              <option value="{{ $num }}" @selected(intval($v('source'))===$num)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Remote ID</label>
          <input name="remote_id" type="number" class="form-control" value="{{ $v('remote_id') }}" placeholder="remote service id if any">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Supplier ID</label>
          <input name="supplier_id" type="number" class="form-control" value="{{ $v('supplier_id') }}">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Local source ID</label>
          <input name="local_source_id" type="number" class="form-control" value="{{ $v('local_source_id') }}">
        </div>

      </div>
    </div>

    {{-- ========== اليمين: Info + main_field/params ========== --}}
    <div class="col-xl-5">
      <label class="form-label mb-1">Info</label>
      <textarea id="infoEditor" class="form-control d-none">{!! $v('info') !!}</textarea>
      <input type="hidden" name="info" id="infoHidden">
      <small class="text-muted d-block mb-2">Description, notes, terms…</small>

      <div class="mt-3">
        <label class="form-label mb-1">Main field</label>
        <textarea name="main_field" class="form-control" rows="2" placeholder="Text or JSON config">{{ $v('main_field') }}</textarea>
      </div>

      <div class="mt-3">
        <label class="form-label mb-1">Params (JSON)</label>
        <textarea name="params" class="form-control" rows="3" placeholder='{"brand":"Apple", "model":"iPhone"}'>{{ $v('params') }}</textarea>
      </div>
    </div>
  </div>

  <div class="mt-4 d-flex justify-content-end gap-2 service-actions">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">{{ $isEdit ? 'Save changes' : 'Create' }}</button>
  </div>
</form>
