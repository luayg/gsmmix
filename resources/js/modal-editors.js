import $ from 'jquery';
window.$ = window.jQuery = $;

// ✅ Lazy loader (مهم جداً مع Vite) عشان نضمن أن window.jQuery موجود قبل summernote
let __snPromise = null;

async function ensureSummernote() {
  // لو شغّال بالفعل
  if ($.fn && typeof $.fn.summernote === 'function') return true;

  // امنع التكرار
  if (!__snPromise) {
    __snPromise = (async () => {
      // css
      await import('summernote/dist/summernote-lite.css');
      // js (بعد تثبيت window.jQuery)
      await import('summernote/dist/summernote-lite.js');
      return true;
    })().catch((e) => {
      console.error('Failed to load Summernote via Vite import()', e);
      return false;
    });
  }

  const ok = await __snPromise;

  // تحقّق نهائي
  if (!ok) return false;
  return !!($.fn && typeof $.fn.summernote === 'function');
}

export async function initModalEditors(container) {
  const root = container instanceof Element ? container : document;
  const areas = root.querySelectorAll('textarea[data-summernote="1"]');
  if (!areas.length) return;

  const ok = await ensureSummernote();
  if (!ok) {
    console.warn('Summernote is not loaded on window.jQuery');
    return;
  }

  $(areas).each(function () {
    const $t = $(this);

    // already initialized
    if ($t.next('.note-editor').length) return;

    const h = Number($t.attr('data-summernote-height') || 360);

    $t.summernote({
      height: h,
      focus: false,
      toolbar: [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['fontsize', ['fontsize']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['height', ['height']],
        ['insert', ['link', 'picture', 'table', 'hr']],
        ['view', ['codeview']]
      ],
      fontSizes: ['8','9','10','11','12','14','16','18','20','24','28','32','36','48'],
      callbacks: {
        onImageUpload: function (files) {
          for (const f of files) {
            const reader = new FileReader();
            reader.onload = (e) => $t.summernote('insertImage', e.target.result);
            reader.readAsDataURL(f);
          }
        }
      }
    });
  });
}
