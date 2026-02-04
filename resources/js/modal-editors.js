// resources/js/modal-editors.js
// Summernote(BS5) loader + initializer (CDN) على نفس jQuery الموجودة في الصفحة

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
  const $ = window.jQuery;
  if (!$) throw new Error('window.jQuery is missing (admin layout must provide it).');

  // إذا Summernote مركّب أصلاً على نفس jQuery → خلاص
  if ($.fn && typeof $.fn.summernote === 'function') return;

  // CSS
  loadCssOnce(
    'orders-sn-bs5-css',
    'https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.css'
  );

  // JS (bs5)
  await loadScriptOnce(
    'orders-sn-bs5-js',
    'https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.js'
  );

  // تأكيد أنه ركب على نفس jQuery
  if (!$.fn || typeof $.fn.summernote !== 'function') {
    throw new Error('Summernote BS5 loaded but not attached to window.jQuery (jQuery duplication issue).');
  }
}

function injectFixCssOnce() {
  if (document.getElementById('orders-sn-fix-z')) return;

  const st = document.createElement('style');
  st.id = 'orders-sn-fix-z';
  st.textContent = `
    .note-editor.note-frame { border: 1px solid #dee2e6; }
    .note-editor .note-toolbar { z-index: 1060; }
    .note-dropdown-menu, .note-modal, .note-popover { z-index: 20000 !important; }
  `;
  document.head.appendChild(st);
}

async function buildOneTextarea(ta) {
  await ensureSummernoteBs5OnSameJquery();
  injectFixCssOnce();

  const $ = window.jQuery;
  const $ta = $(ta);

  // destroy فقط لو summernote فعلاً موجود على نفس jQuery
  try {
    if ($.fn?.summernote && ($ta.next('.note-editor').length || $ta.data('summernote'))) {
      $ta.summernote('destroy');
    }
  } catch (_) {}

  const height = Number(ta.getAttribute('data-summernote-height') || 320);

  // IMPORTANT: textarea لازم تكون ظاهرة داخل modal (shown)
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

  // تحقّق بعد frame (مو فوراً)
  await new Promise((r) => requestAnimationFrame(r));

  const editor = ta.parentElement?.querySelector('.note-editor');
  if (!editor) {
    console.warn('⚠️ Summernote did not build editor for textarea:', ta);
  }
}

export async function initModalEditors(scopeEl = document) {
  const scope = scopeEl instanceof Element ? scopeEl : document;
  const textareas = scope.querySelectorAll('textarea[data-summernote="1"]');
  if (!textareas.length) return;

  for (const ta of textareas) {
    await buildOneTextarea(ta);
  }
}
