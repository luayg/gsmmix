{{-- resources/views/admin/orders/_index.blade.php --}}
@php
  $title       = $title ?? 'Orders';
  $routePrefix = $routePrefix ?? 'admin.orders.imei';
  $kind        = $kind ?? 'imei';

  $perPage = (int)($perPage ?? request('per_page', 20));

  // تنظيف نص عام
  $cleanText = function ($v) {
    $v = (string)$v;
    $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $v = strip_tags($v);
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace("/[ \t]+/", " ", $v) ?? $v;
    $v = preg_replace("/\n{2,}/", "\n", $v) ?? $v;
    return trim($v);
  };

  // استخراج اسم الخدمة حتى لو كان JSON أو فيه entities
  $pickName = function ($v) use ($cleanText) {
    if ($v === null) return '—';
    $raw = html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = trim($raw);
    if ($s !== '' && isset($s[0]) && $s[0] === '{') {
      $j = json_decode($s, true);
      if (is_array($j)) $v = $j['en'] ?? $j['fallback'] ?? reset($j) ?? $raw;
      else $v = $raw;
    } else {
      $v = $raw;
    }
    return $cleanText($v);
  };

  $q      = request('q', '');
  $status = request('status', '');
  $prov   = request('provider', '');

  // ✅ Provider label: لو ما فيه provider و api_order=0 => Manual
  $providerLabel = function ($o) {
    if (!empty($o->provider?->name)) return $o->provider->name;

    $api = (int)($o->api_order ?? 0);
    $supplierId = (int)($o->supplier_id ?? 0);

    if ($api === 0 || $supplierId === 0) return 'Manual';

    return '—';
  };
@endphp

<div class="d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
  <h4 class="mb-0">{{ $title }}</h4>

  {{-- ✅ Toolbar: Show entries + Export + Reload + New order --}}
  <div class="d-flex align-items-center gap-2 flex-wrap">

    {{-- Show entries (server-side pagination) --}}
    <form method="GET" action="{{ route($routePrefix.'.index') }}" class="d-flex align-items-center gap-2 mb-0">
      {{-- حافظ على الفلاتر --}}
      <input type="hidden" name="q" value="{{ $q }}">
      <input type="hidden" name="status" value="{{ $status }}">
      <input type="hidden" name="provider" value="{{ $prov }}">

      <div class="d-flex align-items-center gap-2">
        <span class="text-muted small">Show</span>
        <select name="per_page" class="form-select form-select-sm" style="width: 110px;" onchange="this.form.submit()">
          @foreach([10,25,50,75,100,500,1000] as $n)
            <option value="{{ $n }}" @selected((int)$perPage === (int)$n)>{{ $n }}</option>
          @endforeach
        </select>
        <span class="text-muted small">items</span>
      </div>
    </form>

    {{-- Export dropdown (client-side: Copy/CSV/Print) --}}
    <div class="dropdown">
      <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        Export
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><button class="dropdown-item" type="button" data-export="copy">Copy</button></li>
        <li><button class="dropdown-item" type="button" data-export="csv">CSV</button></li>
        <li><button class="dropdown-item" type="button" data-export="print">Print</button></li>
      </ul>
    </div>

    {{-- Reload dropdown --}}
    <div class="dropdown">
      <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="reloadBtn">
        Reload (Manual)
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><button class="dropdown-item" type="button" data-reload="manual">Manual</button></li>
        <li><button class="dropdown-item" type="button" data-reload="60">Every 1 minute</button></li>
        <li><button class="dropdown-item" type="button" data-reload="300">Every 5 minutes</button></li>
        <li><button class="dropdown-item" type="button" data-reload="600">Every 10 minutes</button></li>
        <li><button class="dropdown-item" type="button" data-reload="1800">Every 30 minutes</button></li>
      </ul>
    </div>

    <button
      class="btn btn-success btn-sm js-open-modal"
      data-url="{{ route($routePrefix . '.modal.create') }}">
      New order
    </button>
  </div>
</div>

{{-- Filters --}}
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" action="{{ route($routePrefix.'.index') }}" class="row g-2 align-items-end">
      <input type="hidden" name="per_page" value="{{ $perPage }}">

      <div class="col-md-4">
        <label class="form-label">Search</label>
        <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="device / email / remote id">
      </div>

      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">All</option>
          @foreach(['waiting','inprogress','success','rejected','cancelled'] as $st)
            <option value="{{ $st }}" @selected($status===$st)>{{ ucfirst($st) }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Provider</label>
        <select name="provider" class="form-select">
          <option value="">All</option>
          @foreach(($providers ?? collect()) as $p)
            <option value="{{ $p->id }}" @selected((string)$prov === (string)$p->id)>{{ $p->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">Apply</button>
        <a class="btn btn-light w-100" href="{{ route($routePrefix.'.index', ['per_page'=>$perPage]) }}">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">

    <div class="table-responsive">
      <table class="table table-striped table-bordered align-middle mb-0" id="ordersTable">
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th style="width:170px;">Date</th>
            <th>Device</th>
            <th>Service</th>
            <th style="width:160px;">Provider</th>
            <th style="width:130px;">Status</th>
            <th style="width:160px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse(($rows ?? []) as $o)
            @php($st = strtolower($o->status ?? 'waiting'))
            <tr>
              <td>{{ $o->id }}</td>
              <td>{{ optional($o->created_at)->format('Y-m-d H:i') }}</td>
              <td>{{ $o->device ?? '-' }}</td>

              <td>
                @if($o->service)
                  {{ $pickName($o->service->name ?? '—') }}
                @else
                  —
                @endif
              </td>

              <td>{{ $providerLabel($o) }}</td>

              <td>
                @if($st === 'success')
                  <span class="badge bg-success">SUCCESS</span>
                @elseif($st === 'rejected')
                  <span class="badge bg-danger">REJECTED</span>
                @elseif($st === 'inprogress')
                  <span class="badge bg-primary">IN PROGRESS</span>
                @elseif($st === 'cancelled')
                  <span class="badge bg-dark">CANCELLED</span>
                @else
                  <span class="badge bg-warning text-dark">WAITING</span>
                @endif
              </td>

              <td class="text-nowrap">
                <a class="btn btn-sm btn-primary js-open-modal"
                   data-url="{{ route($routePrefix . '.modal.view', $o->id) }}">View</a>

                <a class="btn btn-sm btn-warning js-open-order-edit"
                   data-url="{{ route($routePrefix . '.modal.edit', $o->id) }}">Edit</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">No orders</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Orders IMEI Edit Modal (isolated) --}}
    <div class="modal fade" id="orderEditModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content"></div>
      </div>
    </div>

    @if(isset($rows) && method_exists($rows, 'links'))
      <div class="mt-3 d-flex justify-content-center">
        {!! $rows->links('pagination::bootstrap-5') !!}
      </div>
    @endif

  </div>
</div>

<script>
(function () {
  // ---------- Export helpers (client-side) ----------
  function getTableText() {
    const table = document.getElementById('ordersTable');
    if (!table) return '';
    const rows = Array.from(table.querySelectorAll('tr'));
    return rows.map(r => {
      const cols = Array.from(r.querySelectorAll('th,td')).map(c => (c.innerText || '').trim());
      return cols.join('\t');
    }).join('\n');
  }

  function downloadCSV(filename) {
    const table = document.getElementById('ordersTable');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tr'));
    const csv = rows.map(r => {
      const cols = Array.from(r.querySelectorAll('th,td')).map(c => {
        let t = (c.innerText || '').trim();
        t = t.replace(/"/g, '""');
        return `"${t}"`;
      });
      return cols.join(',');
    }).join('\n');

    const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  function doPrint() {
    const table = document.getElementById('ordersTable');
    if (!table) return;
    const w = window.open('', '_blank');
    w.document.write('<html><head><title>Print</title>');
    w.document.write('<style>table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:6px;font-family:Arial;font-size:12px}</style>');
    w.document.write('</head><body>');
    w.document.write(table.outerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    w.print();
    w.close();
  }

  document.querySelectorAll('[data-export]').forEach(btn => {
    btn.addEventListener('click', function () {
      const type = this.getAttribute('data-export');
      if (type === 'copy') {
        const text = getTableText();
        navigator.clipboard?.writeText(text);
      } else if (type === 'csv') {
        downloadCSV('orders.csv');
      } else if (type === 'print') {
        doPrint();
      }
    });
  });

  // ---------- Reload control ----------
  let timer = null;
  const reloadBtn = document.getElementById('reloadBtn');

  function setReloadLabel(sec) {
    if (!reloadBtn) return;
    if (!sec || sec === 'manual') reloadBtn.textContent = 'Reload (Manual)';
    else if (sec == 60) reloadBtn.textContent = 'Reload (Every 1 minute)';
    else if (sec == 300) reloadBtn.textContent = 'Reload (Every 5 minutes)';
    else if (sec == 600) reloadBtn.textContent = 'Reload (Every 10 minutes)';
    else if (sec == 1800) reloadBtn.textContent = 'Reload (Every 30 minutes)';
    else reloadBtn.textContent = 'Reload';
  }

  function applyReloadSetting(sec) {
    if (timer) { clearInterval(timer); timer = null; }

    if (!sec || sec === 'manual') {
      setReloadLabel('manual');
      localStorage.setItem('orders_reload_sec', 'manual');
      return;
    }

    const n = parseInt(sec, 10);
    if (!n || n < 10) {
      setReloadLabel('manual');
      localStorage.setItem('orders_reload_sec', 'manual');
      return;
    }

    setReloadLabel(n);
    localStorage.setItem('orders_reload_sec', String(n));
    timer = setInterval(() => {
      window.location.reload();
    }, n * 1000);
  }

  document.querySelectorAll('[data-reload]').forEach(btn => {
    btn.addEventListener('click', function () {
      const sec = this.getAttribute('data-reload');
      applyReloadSetting(sec);
      if (sec === 'manual') {
        // manual click = reload now
        // (نتركها بدون إعادة تحميل فورية إلا إذا تحب)
      }
    });
  });

  // init from storage
  const saved = localStorage.getItem('orders_reload_sec') || 'manual';
  applyReloadSetting(saved);
})();
</script>
