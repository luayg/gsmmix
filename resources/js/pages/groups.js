/**
 * Helpers for Bootstrap 5 modals
 */
function showModalById(id){
  const el = document.getElementById(id);
  bootstrap.Modal.getOrCreateInstance(el).show();
}
function hideModalById(id){
  const el = document.getElementById(id);
  const inst = bootstrap.Modal.getInstance(el) || bootstrap.Modal.getOrCreateInstance(el);
  inst.hide();
}

/**
 * Wait for jQuery (DataTables relies on it)
 */
function whenJQ(cb, tries=80){
  if (window.$) return cb(window.$);
  if (tries <= 0) return;
  setTimeout(()=>whenJQ(cb, tries-1), 50);
}

(function(){
whenJQ(function($){

  const $page = $('#groupsPage');
  if(!$page.length) return;

  const URL = {
    data: $page.data('url-data'),
    store: $page.data('url-store'),
    show0: $page.data('url-show-base'),
    upd0:  $page.data('url-update-base'),
    del0:  $page.data('url-destroy-base'),
  };

  const table = $('#groupsTable').DataTable({
    processing:true,
    serverSide:true,
    ajax:{ url:URL.data, type:'GET', dataSrc:'data' },
    order:[[0,'asc']],
    columns:[
      {data:'id', name:'id', width:80},
      {data:'name', name:'name'},
      {data:'created', name:'created_at', width:160},
      {data:'actions', orderable:false, searchable:false, width:220}
    ],
    responsive:true,
    pageLength:10
  });

  // Create
  const $mC = $('#modalCreateGroup');
  $('#btnOpenCreate').on('click', function(){
    $mC[0].reset?.();
    $mC.find('.invalid-feedback').text('');
    $mC.find('input').removeClass('is-invalid');
    showModalById('modalCreateGroup');
  });
  $('#formCreateGroup').on('submit', function(e){
    e.preventDefault();
    $.ajax({
      url: URL.store, method:'POST', data: $(this).serialize(),
      headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}
    }).done(function(res){
      hideModalById('modalCreateGroup');
      window.toastr?.success(res?.msg || 'Created');
      table.ajax.reload(null,false);
    }).fail(function(xhr){
      const errs = xhr?.responseJSON?.errors || {};
      if(errs.name){
        const $i=$mC.find('input[name="name"]');
        $i.addClass('is-invalid');
        $i.siblings('.invalid-feedback').text(errs.name[0]);
      }
      window.toastr?.error(xhr?.responseJSON?.message || 'Error');
    });
  });

  // Edit
  const $mE = $('#modalEditGroup');
  $(document).on('click', '.btn-edit-group', function(){
    const id=$(this).data('id'); const name=$(this).data('name');
    $mE.find('input[name="group_id"]').val(id);
    $mE.find('input[name="name"]').val(name);
    $mE.find('.invalid-feedback').text('');
    $mE.find('input').removeClass('is-invalid');
    showModalById('modalEditGroup');
  });
  $('#formEditGroup').on('submit', function(e){
    e.preventDefault();
    const id=$mE.find('input[name="group_id"]').val();
    const url=URL.upd0.replace('/0','/'+id);
    $.ajax({
      url, method:'POST', data: $(this).serialize(),
      headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}
    }).done(function(res){
      hideModalById('modalEditGroup');
      window.toastr?.success(res?.msg || 'Updated');
      table.ajax.reload(null,false);
    }).fail(function(xhr){
      const errs = xhr?.responseJSON?.errors || {};
      if(errs.name){
        const $i=$mE.find('input[name="name"]');
        $i.addClass('is-invalid');
        $i.siblings('.invalid-feedback').text(errs.name[0]);
      }
      window.toastr?.error(xhr?.responseJSON?.message || 'Error');
    });
  });

  // View
  const $mV = $('#modalViewGroup');
  $(document).on('click','.btn-view-group', function(){
    const id=$(this).data('id');
    const url=URL.show0.replace('/0','/'+id);
    $.get(url, function(res){
      for(const k of ['id','name','created_at','updated_at']){
        $mV.find(`[data-v="${k}"]`).text(res?.[k] ?? '-');
      }
      showModalById('modalViewGroup');
    });
  });

  // Delete
  $(document).on('click', '.btn-del-group', function(){
    const id=$(this).data('id'); const name=$(this).data('name');
    if(!confirm(`Delete group "${name}"?`)) return;
    const url=URL.del0.replace('/0','/'+id);
    $.ajax({
      url, method:'POST', data:{_method:'DELETE'},
      headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}
    }).done(function(res){
      if(res?.ok){ window.toastr?.success(res?.msg || 'Deleted'); table.ajax.reload(null,false); }
      else { window.toastr?.warning(res?.msg || 'Cannot delete'); }
    }).fail(function(xhr){
      window.toastr?.error(xhr?.responseJSON?.message || 'Error');
    });
  });

});
})();
