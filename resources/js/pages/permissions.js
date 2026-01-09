// resources/js/pages/permissions.js

(function () {
  function whenReady(cb, tries = 80) {
    if (window.$ && $.fn && $.fn.dataTable) return cb(window.$);
    if (tries <= 0) return;
    setTimeout(() => whenReady(cb, tries - 1), 50);
  }

  whenReady(($) => {
    const $page = $('#permsPage');
    if (!$page.length) return;

    const URL = {
      data:  $page.data('url-data'),
      store: $page.data('url-store'),
      del0:  $page.data('url-destroy-base'),  // .../0
      upd0:  $page.data('url-update-base'),   // .../0
    };

    const table = $('#permsTable').DataTable({
      processing: true,
      serverSide: true,
      responsive: true,
      ajax: { url: URL.data, type: 'GET', dataSrc: 'data' },
      order: [[0, 'asc']],
      columns: [
        { data: 'id',          name: 'id' },
        { data: 'name',        name: 'name' },
        { data: 'guard',       name: 'guard_name' },
        { data: 'roles_count', name: 'roles_count' },
        { data: 'users_count', name: 'users_count' },
        { data: 'created',     name: 'created_at' },
        { data: 'actions',     orderable: false, searchable: false }
      ],
      pageLength: 10,
    });

    // ===== Create =====
    const $mC = $('#modalCreatePerm');
    $('#btnOpenCreate').on('click', function () {
      $mC[0].reset?.();
      $mC.find('.invalid-feedback').text('');
      $mC.find('input').removeClass('is-invalid');
      bootstrap.Modal.getOrCreateInstance($mC[0]).show();
    });

    $('#formCreatePerm').on('submit', function (e) {
      e.preventDefault();
      $.ajax({
        url: URL.store,
        method: 'POST',
        data: $(this).serialize(),
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
      }).done(function (res) {
        bootstrap.Modal.getInstance($mC[0])?.hide();
        table.ajax.reload(null, false);
        window.showToast?.('success', res?.msg || 'Created');
      }).fail(function (xhr) {
        const errs = xhr?.responseJSON?.errors || {};
        if (errs.name) {
          const $i = $mC.find('input[name="name"]');
          $i.addClass('is-invalid');
          $i.siblings('.invalid-feedback').text(errs.name[0]);
        }
        window.showToast?.('danger', xhr?.responseJSON?.message || 'Error', { title: 'Error' });
      });
    });

    // ===== Edit (open) =====
    $(document).on('click', '.js-perm-edit', function (e) {
      e.preventDefault();
      const id    = $(this).data('id');
      const name  = $(this).data('name');
      const guard = $(this).data('guard') || 'web';

      $('#editPermId').val(id);
      $('#editPermName').val(name).removeClass('is-invalid');
      $('#editPermGuard').val(guard);
      $('#formEditPerm .invalid-feedback').text('');

      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditPerm')).show();
    });

    // ===== Edit (submit) =====
    $('#formEditPerm').on('submit', function (e) {
      e.preventDefault();
      const id    = $('#editPermId').val();
      const name  = $('#editPermName').val();
      const guard = $('#editPermGuard').val();
      const token = $('meta[name="csrf-token"]').attr('content') || '';

      const url = (URL.upd0.endsWith('/0') ? URL.upd0.replace(/\/0$/, '/' + id) : (URL.upd0 + '/' + id));

      $.ajax({
        url, method: 'POST',
        headers: { 'X-CSRF-TOKEN': token },
        data: { _method: 'PUT', name, guard_name: guard },
      }).done(function (res) {
        bootstrap.Modal.getInstance(document.getElementById('modalEditPerm'))?.hide();
        table.ajax.reload(null, false);
        window.showToast?.('success', res?.msg || 'Updated');
      }).fail(function (xhr) {
        const errs = xhr?.responseJSON?.errors || {};
        if (errs.name) {
          $('#editPermName').addClass('is-invalid');
          $('#formEditPerm .invalid-feedback').text(errs.name[0]);
        }
        window.showToast?.('danger', xhr?.responseJSON?.message || 'Update failed', { title: 'Error' });
      });
    });

    // ===== Delete (open) =====
    $(document).on('click', '.js-perm-del', function (e) {
      e.preventDefault();
      $('#delPermId').val($(this).data('id'));
      $('#delPermName').text($(this).data('name') || ('#' + $(this).data('id')));
      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDeletePerm')).show();
    });

    // ===== Delete (submit) =====
    $('#formDeletePerm').on('submit', function (e) {
      e.preventDefault();
      const id    = $('#delPermId').val();
      const token = $('meta[name="csrf-token"]').attr('content') || '';

      let url = URL.del0;
      if (!url.endsWith('/0')) url = url.replace(/\/?$/, '/0');
      url = url.replace(/\/0$/, '/' + id);

      $.ajax({
        url, method: 'POST',
        headers: { 'X-CSRF-TOKEN': token },
        data: { _method: 'DELETE' }
      }).done(function (res) {
        bootstrap.Modal.getInstance(document.getElementById('modalDeletePerm'))?.hide();
        table.ajax.reload(null, false);
        window.showToast?.('success', res?.msg || 'Deleted');
      }).fail(function (xhr) {
        window.showToast?.('danger', xhr?.responseText || 'Failed to delete', { title: 'Error' });
      });
    });

  });
})();
