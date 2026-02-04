// resources/js/modal-editors.js
// Isolated Summernote(BS5) loader + initializer (NO jQuery import, NO global override)

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
    s.onerror = (e) => reject(e);
    document.body.appendChild(s);
  });
}

async function ensureSummernoteBs5OnSameJquery() {
  // IMPORTANT: use the page jQuery only
  const $ = window.jQuery;
  if (!$) throw new Error('window.jQuery is missing (admin layout must provide it).');

  // already attached
  if ($.fn && typeof $.fn.summernote === 'function') return;

  // ✅ BS5 build (NOT lite)
  loadCssOnce(
    'orders-sn-bs5-css',
    'https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.css'
  );

  await loadScriptOnce(
    'orders-sn-bs5-js',
    'https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.js'
  );

  if (!$.fn || typeof $.fn.summernote !== 'function') {
    throw new Error('Summernote BS5 loaded but not attached to window.jQuery (possible jQuery duplication).');
  }
}

export async function initModalEditors(scopeEl = document) {
  const scope = scopeEl instanceof Element ? scopeEl : document;

  const textareas = scope.querySelectorAll('textarea[data-summernote="1"]');
  if (!textareas.length) return;

  await ensureSummernoteBs5OnSameJquery();
  const $ = window.jQuery;

  // ✅ زِد الـ z-index عشان القوائم تظهر فوق المودال
  if (!document.getElementById('orders-sn-fix-z')) {
    const st = document.createElement('style');
    st.id = 'orders-sn-fix-z';
    st.textContent = `
      .note-editor.note-frame { border: 1px solid #dee2e6; }
      .note-editor .note-toolbar { z-index: 1060; }
      .note-dropdown-menu, .note-modal, .note-popover { z-index: 20000 !important; }
    `;
    document.head.appendChild(st);
  }

  for (const ta of textareas) {
    const $ta = $(ta);

    // prevent double init
    try {
      if ($ta.next('.note-editor').length || $ta.data('summernote')) {
        $ta.summernote('destroy');
      }
    } catch (_) {}

    const height = Number(ta.getAttribute('data-summernote-height') || 320);

    $ta.summernote({
      height,
      dialogsInBody: true,
      placeholder: ta.getAttribute('placeholder') || '',
      toolbar: [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['fontsize', ['fontsize']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['table', ['table']],
        ['insert', ['link', 'picture']],
        ['view', ['fullscreen', 'codeview']],
        ['misc', ['undo', 'redo', 'help']]
      ]
    });
  }
}
