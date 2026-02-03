// resources/js/modal-editors.js
import $ from 'jquery';
window.$ = window.jQuery = $;

// تحميل Summernote مرة واحدة فقط (ديناميكياً بعد تثبيت window.jQuery)
let _summernotePromise = null;

async function ensureSummernoteLoaded() {
  if (_summernotePromise) return _summernotePromise;

  _summernotePromise = (async () => {
    // CSS
    await import('summernote/dist/summernote-lite.css');

    // مهم جداً: JS بعد تثبيت window.jQuery
    await import('summernote/dist/summernote-lite.js');

    if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.summernote !== 'function') {
      console.warn('Summernote failed to attach to window.jQuery');
      return false;
    }
    return true;
  })();

  return _summernotePromise;
}

// Toolbar مثل المثال (قريب جداً من صورة 77)
function getToolbar() {
  return [
    ['style', ['style']],
    ['font', ['bold', 'italic', 'underline', 'clear']],
    ['fontname', ['fontname']],
    ['fontsize', ['fontsize']],
    ['color', ['color']],
    ['para', ['ul', 'ol', 'paragraph']],
    ['height', ['height']],
    ['table', ['table']],
    ['insert', ['link', 'picture', 'video']],
    ['view', ['fullscreen', 'codeview']]
  ];
}

// تهيئة Summernote داخل أي Container (مودال/صفحة)
export async function initModalEditors(root = document) {
  const ok = await ensureSummernoteLoaded();
  if (!ok) return;

  const scope = root instanceof Element ? root : document;

  const editors = scope.querySelectorAll('textarea[data-summernote="1"]');

  editors.forEach((ta) => {
    const $ta = window.jQuery(ta);

    // لو متفعل من قبل، لا تعيد تفعيله
    if ($ta.data('summernote')) return;

    const height = Number(ta.getAttribute('data-summernote-height') || 320);

    $ta.summernote({
      height,
      tabsize: 2,
      toolbar: getToolbar(),
      // ملاحظة: لو تبي تمنع الكود-فيو نهائياً احذف 'codeview' من toolbar
    });
  });
}
