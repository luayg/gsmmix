// resources/js/admin/remote-server-clone.js
// الصفحة: admin.api.remote.server.index

// لا نضيف هنا Import ولا Wizard
// فقط نترك service-modal.blade.php يلتقط data-create-service

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.js-clone-server[data-create-service]');
  if (!btn) return;

  // ملاحظة: service-modal.blade.php عندك أصلاً يستمع لـ [data-create-service]
  // هذا الملف فقط موجود لإثبات الفصل + (لو بدك لاحقاً تضيف تحسينات خاصة بالـ server clone)
});
