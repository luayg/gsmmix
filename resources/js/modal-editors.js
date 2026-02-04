// resources/js/modal-editors.js
let SN_LOADING = null;

function loadCssOnce(id, href) {
  if (document.getElementById(id)) return;
  const l = document.createElement('link');
  l.id = id;
  l.rel = 'stylesheet';
  l.href = href;
  document.head.appendChild(l);
}

function loadScriptOnce(id, src) {
  return new Promise((resolve, reject) => {
    if (document.getElementById(id)) return resolve();
    const s = document.createElement('script');
    s.id = id;
    s.src = src;
    s.async = false;
    s.onload = () => resolve();
    s.onerror = () => reject(new Error('Failed to load: ' + src));
    document.body.appendChild(s);
  });
}

async function ensureSummernoteLoaded() {
  // لازم يكون موجود jQuery الأساسي من admin.js
  if (!window.jQuery) {
    console.warn('[modal-editors] window.jQuery is missing. (admin.js should provide it)');
    return false;
  }

  // لو Summernote موجود على نفس jQuery خلاص
  if (window.jQuery.fn && typeof window.jQuery.fn.summernote === 'function') {
    return true;
  }

  // تحميل مرة واحدة فقط
  if (!SN_LOADING) {
    SN_LOADING = (async () => {
      loadCssOnce(
        'summernote-lite-css',
        'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css'
      );

      await loadScriptOnce(
        'summernote-lite-js',
        'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js'
      );

      // تأكيد أنه تركّب على نفس jQuery
      if (!window.jQuery.fn || typeof window.jQuery.fn.summernote !== 'function') {
        throw new Error('Summernote did not attach to window.jQuery');
      }
    })().catch((e) => {
      console.error('[modal-editors] ', e);
      return false;
    });
  }

  const ok = await SN_LOADING;
  return ok !== false;
}

export async function initModalEditors(container) {
  const root = container instanceof Element ? container : document;

  // ندعم الحالتين: data-summernote أو class js-editor
  const areas = root.querySelectorAll('textarea[data-summernote="1"], textarea.js-editor');
  if (!areas.length) return;

  const ok = await ensureSummernoteLoaded();
  if (!ok) return;

  const $ = window.jQuery;

  $(areas).each(function () {
    const $t = $(this);

    // already initialized
    if ($t.next('.note-editor').length) return;

    const h = Number($t.attr('data-summernote-height') || 320);

    $t.summernote({
      height: h,
      focus: false,
      toolbar: [
        ['fontsize', ['fontsize']],
        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
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
