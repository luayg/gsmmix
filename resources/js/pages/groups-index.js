// resources/js/pages/groups-index.js
import $ from 'jquery';

$(function(){
  const $page = $('#groupsPage'); 
  if (!$page.length) return;

  const dt = $('#groupsTable').DataTable({
    processing: true,
    serverSide: true,
    responsive: true,
    ajax: { url: $page.data('url-data'), type: 'GET' },
    order: [[0,'asc']],
    columns: [
      { data: 'id',      name: 'id' },
      { data: 'name',    name: 'name' },
      { data: 'users',   name: 'users', orderable: false, searchable: false },
      { data: 'created', name: 'created_at' },
      { data: 'actions', name: 'actions', orderable: false, searchable: false }
    ]
  });

  // لا حاجة لمعالجات إضافية هنا؛
  // الأزرار تستخدم .js-open-modal ويقوم admin.js بكل شيء (تحميل المودال + submit + refresh).
});
