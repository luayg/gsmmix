// resources/js/pages/roles.js

// Helpers: Bootstrap modals
function showModal(id){ const el=document.getElementById(id); bootstrap.Modal.getOrCreateInstance(el).show(); }
function hideModal(id){ const el=document.getElementById(id); bootstrap.Modal.getOrCreateInstance(el).hide(); }

// انتظر jQuery (لأن DataTables عليه)
function whenJQ(cb, tries=80){
  if (window.$) return cb(window.$);
  if (tries <= 0) return;
  setTimeout(()=>whenJQ(cb, tries-1), 50);
}

(function(){
  whenJQ(function($){

    const $page = $('#rolesPage');
    if(!$page.length) return;

    // CSRF & Ajax headers
    $.ajaxSetup({
      headers:{
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      cache:false
    });

    const URL = {
      data:   $page.data('url-data'),
      store:  $page.data('url-store'),
      upd0:   $page.data('url-update-base'),
      del0:   $page.data('url-destroy-base'),
      perms0: $page.data('url-perms-base')
    };

    // لا تعيد تهيئة الجدول إن كان مهيأ
    let table;
    if ($.fn.DataTable.isDataTable('#rolesTable')) {
      table = $('#rolesTable').DataTable();
    } else {
      table = $('#rolesTable').DataTable({
        processing:true,
        serverSide:true,
        ajax:{ url:URL.data, type:'GET', dataSrc:'data', cache:false },
        order:[[1,'asc']],
        columns:[
          {data:'id',      name:'id'},
          {data:'name',    name:'name'},
          {data:'perms',   name:'permissions_count'},
          {data:'created', name:'created_at'},
          {data:'actions', orderable:false, searchable:false}
        ],
        responsive:true,
        pageLength:10
      });
    }

    // Helper عرض توستر آمن (fallback للـ alert لو ما فيه showToast)
    function toast(type, msg, opts){
      if (window.showToast) {
        window.dispatchEvent?.(new Event('show-toast-reposition'));
        window.showToast(type, msg, opts || {});
      } else {
        alert(msg);
      }
    }

    // =========================
    // Create
    // =========================
    const $mC = $('#modalCreateRole');
    $('#btnOpenCreate').on('click', function(){
      $mC[0].reset?.();
      $mC.find('.invalid-feedback').text('');
      $mC.find('input').removeClass('is-invalid');
      showModal('modalCreateRole');
    });

    $('#formCreateRole').on('submit', function(e){
      e.preventDefault();
      const $btn = $(this).find('button[type=submit]');
      $btn.prop('disabled', true);
      $btn.find('.btn-text').addClass('d-none');
      $btn.find('.spinner-border').removeClass('d-none');

      $.ajax({
        url:URL.store,
        method:'POST',
        data: $(this).serialize(),
        dataType:'json'
      }).done(function(res){
        hideModal('modalCreateRole');
        table.ajax.reload(null,false);
        toast('success', res?.msg || 'Role created');
      }).fail(function(xhr){
        const errs = xhr?.responseJSON?.errors || {};
        const $name = $mC.find('input[name="name"]');
        if (errs.name){
          $name.addClass('is-invalid');
          $name.siblings('.invalid-feedback').text(errs.name[0]);
        }
        const msg = Object.values(errs).flat().join('\n') || xhr?.responseJSON?.message || 'Error';
        toast('danger', msg, { title:'Error', delay:5000 });
      }).always(function(){
        $btn.prop('disabled', false);
        $btn.find('.btn-text').removeClass('d-none');
        $btn.find('.spinner-border').addClass('d-none');
      });
    });

    // =========================
    // Edit
    // =========================
    const $mE = $('#modalEditRole');
    $(document).on('click', '.btn-edit-role', function(){
      const id=$(this).data('id');
      const name=$(this).data('name');
      $mE.find('input[name="role_id"]').val(id);
      $mE.find('input[name="name"]').val(name);
      $mE.find('.invalid-feedback').text('');
      $mE.find('input').removeClass('is-invalid');
      showModal('modalEditRole');
    });

    $('#formEditRole').on('submit', function(e){
      e.preventDefault();
      const id=$mE.find('input[name="role_id"]').val();
      const url=URL.upd0.replace('/0','/'+id);

      const $btn = $(this).find('button[type=submit]');
      $btn.prop('disabled', true);
      $btn.find('.btn-text').addClass('d-none');
      $btn.find('.spinner-border').removeClass('d-none');

      $.ajax({
        url,
        method:'POST',          // سيصل للراوت كـ PUT بسبب @method('PUT') الموجود في الفورم
        data: $(this).serialize(),
        dataType:'json'
      }).done(function(res){
        hideModal('modalEditRole');
        table.ajax.reload(null,false);
        toast('success', res?.msg || 'Role updated');
      }).fail(function(xhr){
        const errs=xhr?.responseJSON?.errors||{};
        const $name=$mE.find('input[name="name"]');
        if(errs.name){
          $name.addClass('is-invalid');
          $name.siblings('.invalid-feedback').text(errs.name[0]);
        }
        const msg = Object.values(errs).flat().join('\n') || xhr?.responseJSON?.message || 'Error';
        toast('danger', msg, { title:'Error', delay:5000 });
      }).always(function(){
        $btn.prop('disabled', false);
        $btn.find('.btn-text').removeClass('d-none');
        $btn.find('.spinner-border').addClass('d-none');
      });
    });

    // =========================
    // View (بشكل بسيط)
    // =========================
    const $mV = $('#modalViewRole');
    $(document).on('click','.btn-view-role', function(){
      const id=$(this).data('id');
      const name=$(this).data('name');
      const perms=$(this).data('perms');
      $('#roleViewBody').html(`
        <div class="row">
          <div class="col-md-6"><strong>Name:</strong> ${name}</div>
          <div class="col-md-6"><strong># Permissions:</strong> ${perms}</div>
        </div>
      `);
      showModal('modalViewRole');
    });

    // =========================
    // Delete (مودال تأكيد)
    // =========================
    let deletingId = null;
    const $mD = $('#modalDeleteRole');
    $('#btnConfirmDelete').on('click', function(){
      if(!deletingId) return;
      const url = URL.del0.replace('/0','/'+deletingId);
      $(this).prop('disabled', true);

      $.ajax({
        url, method:'POST', data:{ _method:'DELETE' }, dataType:'json'
      }).done(function(res){
        hideModal('modalDeleteRole');
        deletingId = null;
        $('#btnConfirmDelete').prop('disabled', false);
        table.ajax.reload(null,false);
        toast('success', res?.msg || 'Role deleted');
      }).fail(function(xhr){
        const msg = xhr?.responseJSON?.message || 'Error';
        toast('danger', msg, { title:'Error', delay:5000 });
        $('#btnConfirmDelete').prop('disabled', false);
      });
    });

    $(document).on('click','.btn-del-role', function(){
      deletingId = $(this).data('id');
      $('#delRoleName').text($(this).data('name'));
      showModal('modalDeleteRole');
    });

  });
})();
