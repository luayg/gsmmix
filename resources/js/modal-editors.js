// resources/js/modal-editors.js
// Isolated Summernote loader + initializer (NO jQuery import, NO global override)

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

async function ensureSummernoteOnSameJquery() {
  // IMPORTANT: use the page jQuery only
  const $ = window.jQuery;
  if (!$) throw new Error('window.jQuery is missing (admin layout must provide it).');

  if ($.fn && typeof $.fn.summernote === 'function') return;

  // Load summernote assets ONCE (no jQuery load here!)
  loadCssOnce('orders-sn-css', 'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css');
  await loadScriptOnce('orders-sn-js', 'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js');

  // After load, verify it attached to SAME jQuery
  if (!$.fn || typeof $.fn.summernote !== 'function') {
    throw new Error('Summernote loaded but not attached to window.jQuery (possible jQuery duplication).');
  }
}

export async function initModalEditors(scopeEl = document) {
  const scope = scopeEl instanceof Element ? scopeEl : document;

  // Find editors
  const textareas = scope.querySelectorAll('textarea[data-summernote="1"]');
  if (!textareas.length) return;

  await ensureSummernoteOnSameJquery();
  const $ = window.jQuery;

  for (const ta of textareas) {
    const $ta = $(ta);

    // prevent double init
    try {
      if ($ta.next('.note-editor').length || $ta.data('summernote')) {
        $ta.summernote('destroy');
      }
    } catch (_) {}

    const height = Number(ta.getAttribute('data-summernote-height') || 260);

    $ta.summernote({
      height,
      dialogsInBody: true,
      placeholder: ta.getAttribute('placeholder') || '',
      toolbar: [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['table', ['table']],
        ['insert', ['link', 'picture']],
        ['view', ['codeview']],
        ['help', ['help']],
        ['history', ['undo', 'redo']]
      ]
    });
  }
}
