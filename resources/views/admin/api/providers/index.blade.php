@extends('layouts.admin')

@section('title', 'API Management')

@section('content')
@php
  // قيَم واجهة الاستخدام
  $q       = request('q', '');
  $type    = request('type', '');
  $status  = request('status', '');
  $perPage = (int) request('per_page', method_exists($rows, 'perPage') ? $rows->perPage() : 20);
@endphp

<div class="page-apis-index"><!-- نطاق الصفحة -->

  <div class="card">
    {{-- ===== شريط الأدوات (الأزرق) ===== --}}
    <div class="card-header">
      <div class="apis-toolbar p-2 px-3 rounded" style="background:linear-gradient(180deg,#2d6cdf,#2563eb);color:#fff;">
        <div class="row g-2 align-items-center">
          <div class="col-auto d-flex align-items-center gap-2">

            {{-- Show N items --}}
            <div class="btn-group">
              <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                Show {{ $perPage }} items
              </button>
              <ul class="dropdown-menu">
                @foreach([10,20,25,50,100] as $n)
                  <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['per_page'=>$n]) }}">Show {{ $n }} items</a></li>
                @endforeach
              </ul>
            </div>

            {{-- Add --}}
            <a href="{{ route('admin.apis.create') }}"
               class="btn btn-success btn-sm js-open-modal"
               data-url="{{ route('admin.apis.create') }}">Add API</a>

            {{-- Export --}}
            <button id="btn-export" type="button" class="btn btn-outline-light btn-sm">
              Export CSV (current view)
            </button>
          </div>

          {{-- Filters --}}
          <div class="col-auto">
            <form class="d-flex align-items-center gap-2" method="GET" action="{{ route('admin.apis.index') }}">
              <select name="type" class="form-select form-select-sm" style="min-width:150px" onchange="this.form.submit()">
                <option value="">DHRU / Simple link</option>
                <option value="DHRU"        {{ $type==='DHRU' ? 'selected' : '' }}>DHRU</option>
                <option value="Simple link" {{ $type==='Simple link' ? 'selected' : '' }}>Simple link</option>
              </select>
              <select name="status" class="form-select form-select-sm" style="min-width:150px" onchange="this.form.submit()">
                <option value="">Status</option>
                <option value="Active"   {{ $status==='Active' ? 'selected' : '' }}>Active</option>
                <option value="Inactive" {{ $status==='Inactive' ? 'selected' : '' }}>Inactive</option>
              </select>
              <input type="hidden" name="per_page" value="{{ $perPage }}">
            </form>
          </div>

          {{-- Search --}}
          <div class="col ms-auto">
            <form method="GET" action="{{ route('admin.apis.index') }}" class="d-flex justify-content-end">
              <div class="input-group input-group-sm" style="max-width:300px;">
                <span class="input-group-text bg-white">Search</span>
                <input type="text" name="q" class="form-control" placeholder="Smart search" value="{{ $q }}">
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>

    {{-- ===== Flash ===== --}}
    <div class="card-body p-0">
      @if(session('ok'))
        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
          {{ session('ok') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      @endif

      {{-- ===== الجدول ===== --}}
      <div class="table-responsive apis-scroll" style="overflow-x:auto; overflow-y:visible;">
        <table class="table table-striped mb-0 align-middle apis-table" id="apis-table">
          <thead class="bg-light">
            <tr>
              <th class="sortable" data-sort="id"     style="width:90px">ID <span class="sort-icon"></span></th>
              <th class="sortable" data-sort="name">Name <span class="sort-icon"></span></th>
              <th class="sortable" data-sort="type"   style="width:140px">Type <span class="sort-icon"></span></th>
              <th style="width:110px">Synced</th>
              <th style="width:120px">Auto sync</th>
              <th class="text-end sortable" data-sort="balance" style="width:140px">Balance <span class="sort-icon"></span></th>
              <th class="text-end" style="width:380px">Actions</th>
            </tr>
          </thead>
          <tbody id="apis-tbody">
            @forelse($rows as $p)
              @php
                $isSynced   = (bool)($p->synced    ?? $p->is_synced    ?? $p->has_synced ?? false);
                $isAuto     = (bool)($p->auto_sync ?? $p->is_auto_sync ?? $p->autosync   ?? false);
                $balanceVal = (float)($p->balance  ?? 0);
              @endphp
              <tr data-id="{{ $p->id }}"
                  data-name="{{ \Illuminate\Support\Str::lower($p->name) }}"
                  data-type="{{ \Illuminate\Support\Str::lower($p->type) }}"
                  data-balance="{{ $balanceVal }}">
                <td class="apis-id"><code>{{ $p->id }}</code></td>
                <td dir="auto">{{ $p->name }}</td>
                <td class="text-uppercase">{{ $p->type }}</td>

                <td>{!! $isSynced ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning text-dark">No</span>' !!}</td>
                <td>{!! $isAuto   ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning text-dark">No</span>' !!}</td>

                <td class="text-end {{ $balanceVal > 0 ? '' : 'text-danger' }}">
                  ${{ number_format($balanceVal, 2) }}
                </td>

                <td class="text-end">
                  <div class="btn-group btn-group-sm" role="group" aria-label="API actions">
                    {{-- View --}}
                    <a href="#" class="btn btn-primary js-open-modal"
                       data-url="{{ route('admin.apis.view', $p) }}">View</a>

                    {{-- Services (DropDown Bootstrap قياسي) --}}
                    <div class="btn-group position-static" role="group">
                      <button type="button"
                              class="btn btn-secondary dropdown-toggle"
                              data-bs-toggle="dropdown"
                              aria-expanded="false">
                        Services
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item js-open-modal" href="#" data-url="{{ route('admin.apis.services.imei', $p) }}">IMEI</a></li>
                        <li><a class="dropdown-item js-open-modal" href="#" data-url="{{ route('admin.apis.services.server', $p) }}">Server</a></li>
                        <li><a class="dropdown-item js-open-modal" href="#" data-url="{{ route('admin.apis.services.file', $p) }}">File</a></li>
                      </ul>
                    </div>

                    {{-- Sync now --}}
                    <form action="{{ route('admin.apis.sync', $p) }}" method="POST" class="d-inline-block">
                      @csrf
                      <button type="submit" class="btn btn-info">Sync now</button>
                    </form>

                    {{-- Edit --}}
                    <a href="#" class="btn btn-warning js-open-modal"
                       data-url="{{ route('admin.apis.edit', $p) }}">Edit</a>

                    {{-- Delete --}}
                    <form action="{{ route('admin.apis.destroy', $p) }}" method="POST" class="d-inline-block"
                          onsubmit="return confirm('Delete this API?')">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-4">No APIs found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- ===== التذييل (عدد العناصر والصفحات) ===== --}}
      <div class="d-flex align-items-center justify-content-between flex-wrap p-3 gap-2">
        <div class="text-muted small">
          Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} items
        </div>
        <div>
          {{ $rows->appends(['q'=>$q,'type'=>$type,'status'=>$status,'per_page'=>$perPage])->links() }}
        </div>
      </div>
    </div>
  </div>

</div><!-- /.page-apis-index -->
@endsection


@push('styles')
<style>
  /* ===== ضبط أعمدة الدِّسكتوب كما هي ===== */
  :root{
    --apis-name-col: 280px; /* عدّل الرقم لتكبير/تصغير عمود الاسم على الدِّسكتوب */
  }

  .page-apis-index .apis-table th:nth-child(2),
  .page-apis-index .apis-table td:nth-child(2){
    width: var(--apis-name-col);
    max-width: var(--apis-name-col);
  }

  .page-apis-index .apis-table td:nth-child(2){
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .page-apis-index .apis-table th:nth-child(3),
  .page-apis-index .apis-table td:nth-child(3){ width: 110px; } /* Type */
  .page-apis-index .apis-table th:nth-child(4),
  .page-apis-index .apis-table td:nth-child(4){ width: 80px; }  /* Synced */
  .page-apis-index .apis-table th:nth-child(5),
  .page-apis-index .apis-table td:nth-child(5){ width: 100px; } /* Auto sync */
  .page-apis-index .apis-table th:nth-child(6),
  .page-apis-index .apis-table td:nth-child(6){ width: 120px; } /* Balance */
  .page-apis-index .apis-table th:nth-child(7),
  .page-apis-index .apis-table td:nth-child(7){ width: 360px; } /* Actions */

  /* منع أي سكرول عمودي داخلي يقطع القوائم */
  .page-apis-index .content-wrapper,
  .page-apis-index .card,
  .page-apis-index .card-body,
  .page-apis-index .table-responsive{
    max-height: none !important;
    overflow: visible !important;
  }

  /* قوائم الدروبداون فوق الكل على الدِّسكتوب */
  .page-apis-index .btn-group.position-static,
  .page-apis-index .dropdown.position-static{
    position: static !important;
  }
  .page-apis-index .dropdown-menu{
    z-index: 2050 !important;
  }

  /* تحسينات شكلية بسيطة */
  .apis-table .apis-id{font-family:ui-monospace,Menlo,Consolas,monospace}
  .apis-table thead th{user-select:none}
  .apis-table thead th .sort-icon{margin-left:.35rem;opacity:.6}
  .apis-table thead th.sort-asc .sort-icon::after{content:"▲";font-size:.7rem}
  .apis-table thead th.sort-desc .sort-icon::after{content:"▼";font-size:.7rem}

  /* =========================================================
     موبايل (sm-) — تحويل الجدول إلى كروت قابلة للقراءة بلا سكرول
     ========================================================= */
  @media (max-width: 575.98px){

    /* ألغِ التمرير الأفقي من الحاوية الجدولية على الجوال */
    .page-apis-index .apis-scroll{ overflow-x: visible !important; }

    /* تجاهل جميع قيود العرض الخاصة بالدِّسكتوب */
    .page-apis-index .apis-table th,
    .page-apis-index .apis-table td{
      width:auto !important;
      max-width:none !important;
      white-space: normal !important;
    }

    /* اخفِ الهيدر، وحوّل عناصر الجدول إلى بلوكات */
    .apis-table thead{ display:none; }
    .apis-table,
    .apis-table tbody,
    .apis-table tr,
    .apis-table td{ display:block; width:100%; }

    /* الكارت لكل صف */
    .apis-table tr{
      margin: .65rem .25rem;
      border: 1px solid #e9ecef;
      border-radius: .6rem;
      box-shadow: 0 2px 8px rgba(0,0,0,.04);
      background: #fff;
      overflow: hidden; /* يمنع أي نتوء عند الأزرار */
    }

    /* كل خلية = سطر من عمودين: وسم ثابت + قيمة */
    .apis-table td{
      display: grid;
      grid-template-columns: 92px 1fr; /* قلّلها/كبّرها لو حاب تقرّب/تبعد الوسم */
      gap: .35rem;
      align-items: center;
      padding: .6rem .75rem;
      border-bottom: 1px solid #f1f3f5;
    }
    .apis-table td:last-child{ border-bottom: none; }

    /* الوسم قبل القيمة – نحاول أولًا من data-label،
       وإن لم توجد نضع نصًا حسب العمود */
    .apis-table td::before{
      content: attr(data-label);
      font-weight: 600;
      color: #6c757d;
    }
    .apis-table td:nth-child(1)::before{ content:"ID"; }
    .apis-table td:nth-child(2)::before{ content:"Name"; }
    .apis-table td:nth-child(3)::before{ content:"Type"; }
    .apis-table td:nth-child(4)::before{ content:"Synced"; }
    .apis-table td:nth-child(5)::before{ content:"Auto sync"; }
    .apis-table td:nth-child(6)::before{ content:"Balance"; }
    .apis-table td:nth-child(7)::before{ content:"Actions"; }

    /* اسم الـAPI يلتف بسلاسة */
    .page-apis-index .apis-table td:nth-child(2){
      white-space: normal !important;
      overflow: visible !important;
      text-overflow: clip !important;
    }

    /* الشارات (Yes/No) بحجم طبيعي */
    .page-apis-index .badge{
      display: inline-block !important;
      min-width: 2.2rem;
      padding: .35em .55em;
      font-size: .85em;
    }

    /* زر الإجراءات ياخذ عرض البطاقة بالكامل */
    .page-apis-index .apis-table td:nth-child(7) .btn-group{
      display: block;
      width: 100%;
    }
    .page-apis-index .apis-table td:nth-child(7) .btn-group > *{
      width: 100%;
      margin-bottom: .35rem;
    }
    .page-apis-index .apis-table td:nth-child(7) .btn-group > *:last-child{
      margin-bottom: 0;
    }
  }
</style>
@endpush



@push('scripts')
<script>
(function(){
  /* فرز بسيط في الواجهة */
  const tbody = document.getElementById('apis-tbody');
  const table = document.getElementById('apis-table');
  if (tbody && table){
    let currentSort = { key:null, dir:'asc' };
    function cmp(a,b,key,dir){
      const m = dir==='asc'?1:-1;
      if(key==='balance'){ const x=+a.dataset.balance||0, y=+b.dataset.balance||0; return (x<y?-1:x>y?1:0)*m; }
      if(key==='id'){ const x=+a.dataset.id||0, y=+b.dataset.id||0; return (x-y)*m; }
      return ((a.dataset[key]||'').localeCompare(b.dataset[key]||''))*m;
    }
    function setIcon(th,dir){
      table.querySelectorAll('thead th').forEach(el=>el.classList.remove('sort-asc','sort-desc'));
      th.classList.add(dir==='asc'?'sort-asc':'sort-desc');
    }
    table.querySelectorAll('thead th.sortable').forEach(th=>{
      th.addEventListener('click', ()=>{
        const key = th.getAttribute('data-sort');
        currentSort.dir = (currentSort.key===key && currentSort.dir==='asc') ? 'desc' : 'asc';
        currentSort.key = key;
        Array.from(tbody.querySelectorAll('tr')).sort((r1,r2)=>cmp(r1,r2,key,currentSort.dir)).forEach(r=>tbody.appendChild(r));
        setIcon(th,currentSort.dir);
      });
    });
  }

  /* تصدير CSV للعرض الحالي */
  document.getElementById('btn-export')?.addEventListener('click', ()=>{
    const headers=['ID','Name','Type','Synced','Auto sync','Balance'];
    const lines=[headers.join(',')];
    document.querySelectorAll('#apis-tbody tr').forEach(tr=>{
      const td=tr.querySelectorAll('td'); if(td.length<6) return;
      const id=td[0].innerText.trim(), name=td[1].innerText.trim().replace(/\s+/g,' '),
            type=td[2].innerText.trim(), synced=td[3].innerText.trim(),
            auto=td[4].innerText.trim(), bal=td[5].innerText.trim().replace(/[$,]/g,'');
      lines.push([id,name,type,synced,auto,bal].map(v=>`"${v.replace(/"/g,'""')}"`).join(','));
    });
    const blob=new Blob([lines.join('\r\n')],{type:'text/csv;charset=utf-8;'});
    const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='apis_current_view.csv';
    document.body.appendChild(a); a.click(); URL.revokeObjectURL(a.href); a.remove();
  });
})();
</script>
@endpush
