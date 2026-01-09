// resources/js/pages/users-index.js
import $ from 'jquery';

$(function () {
  const $page = $('#usersPage');
  if (!$page.length) return;

  const urls = {
    data   : $page.data('url-data'),
    roles  : $page.data('url-roles'),
    groups : $page.data('url-groups'),
    create : $page.data('url-create') || '/admin/users/modal/create',
  };

  // أخفي زر Create user الزائد في أعلى اليمين (إن وُجد)
  $('.content-wrapper .btn.btn-success:contains("Create user")').first().hide();

  // ===== شريط الأدوات =====
  const toolbarHtml = `
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3" id="usersToolbar">
      <div id="btnPageLength"></div>
      <div id="btnColVis"></div>
      <button id="btnResetCols" class="btn btn-light border btn-sm">Reset columns</button>
      <div id="btnExport"></div>

      <button id="btnSpecial" class="btn btn-soft-warning btn-sm">Users with special prices</button>

      <button class="btn btn-soft-success btn-sm js-open-modal" data-url="${urls.create}">
        <i class="fas fa-plus"></i> Create user
      </button>

      <div class="ms-auto" style="min-width:240px">
        <input id="globalSearch" type="text" class="form-control form-control-sm" placeholder="Search...">
      </div>
    </div>`;
  $('#usersPage .card-body').prepend(toolbarHtml);

  // ===== DataTable =====
  const $table = $('#usersTable');
  const dt = $table.DataTable({
    processing: true,
    serverSide: true,
    responsive: true,

    // ⬇️ إضافة info + pagination في أسفل الجدول
    dom: 't<"dt-bottom d-flex justify-content-between align-items-center mt-3"ip>',

    ajax: {
      url: urls.data,
      type: 'GET',
      error: (xhr) => {
        console.error('DataTable AJAX error:', xhr?.responseText || xhr.statusText);
        window.showToast?.('Failed to load users', { title: 'Error', delay: 4000, variant: 'danger' });
      }
    },
    order: [[0,'asc']],
    columns: [
      { data: 'id',       name: 'id' },
      { data: 'name',     name: 'name' },
      { data: 'email',    name: 'email' },
      { data: 'username', name: 'username' },
      { data: 'roles',    name: 'roles',  orderable: false, searchable: false },
      { data: 'group',    name: 'group',  orderable: false, searchable: false },
      { data: 'balance',  name: 'balance', className: 'text-end' },
      { data: 'status',   name: 'status' },
      { data: 'actions',  name: 'actions', orderable: false, searchable: false }
    ]
  });

  // ===== أزرار DataTables =====
  if ($.fn.dataTable.Buttons) {
    const bl = new $.fn.dataTable.Buttons(dt, {
      buttons: [{ extend: 'pageLength', className: 'btn btn-soft-secondary btn-sm' }]
    }).container();
    $('#btnPageLength').append(bl);

    const bc = new $.fn.dataTable.Buttons(dt, {
      buttons: [{
        extend: 'colvis',
        text: 'Column visibility',
        className: 'btn btn-soft-secondary btn-sm',
        columns: ':not(:last-child)'
      }]
    }).container();
    $('#btnColVis').append(bc);

    const be = new $.fn.dataTable.Buttons(dt, {
      buttons: [{ extend: 'csv', className: 'btn btn-soft-primary btn-sm', text: 'Export CSV' }]
    }).container();
    $('#btnExport').append(be);
  } else {
    console.warn('DataTables Buttons plugin is missing.');
    $('#btnPageLength,#btnColVis,#btnExport').remove();
  }

  // Reset columns
  $('#btnResetCols').on('click', () => {
    dt.columns().visible(true);
    dt.order([[0,'asc']]).draw(false);
  });

  // البحث العام
  $('#globalSearch').on('keyup', function () {
    dt.search(this.value).draw();
  });

  // فتح المودالات (بدون كاش للـ Finances لضمان صحة المستخدم)
  $(document).on('click', '.js-open-modal', function (e) {
    e.preventDefault();
    const url = $(this).data('url') || $(this).attr('data-url');
    if (!url) return;

    const $m = $('#ajaxModal');
    const $c = $m.find('.modal-content');
    $c.html(`
      <div class="p-5 text-center text-muted">
        <div class="spinner-border" role="status"></div>
        <div class="mt-3">Loading…</div>
      </div>
    `);
    window.bootstrap.Modal.getOrCreateInstance($m[0]).show();

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
      .then(r => r.text())
      .then(html => $c.html(html))
      .catch(() => $c.html('<div class="p-4 text-danger">Failed to load.</div>'));
  });
});
