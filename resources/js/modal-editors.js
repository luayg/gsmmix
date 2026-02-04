import $ from 'jquery';

// ✅ لا تلمس global إذا مش موجود (لكن Summernote يحتاج window.jQuery غالباً)
if (!window.jQuery) {
  window.$ = window.jQuery = $;
}

// Helpers: load once
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
    s.onload = resolve;
    s.onerror = reject;
    document.body.appendChild(s);
  });
}

// ✅ تحميل Summernote عند الحاجة (معزول لهذه الصفحة فقط لأن entry خاص بها)
async function ensureSummernoteLoaded() {
  // CSS
  loadCssOnce(
    'sn-lite-css',
    'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css'
  );

  // JS (summernote يحتاج jQuery global)
  if (!window.jQuery) window.$ = window.jQuery = $;

  if (!window.jQuery.fn || typeof window.jQuery.fn.summernote !== 'function') {
    await loadScriptOnce(
      'sn-lite-js',
      'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js'
    );
  }

  return !!(window.jQuery.fn && typeof window.jQuery.fn.summernote === 'function');
}

// Toolbar مثل الصورة 77 (قريب جداً)
function summernoteOptions(height = 360) {
  return {
    height,
    tabsize: 2,
    toolbar: [
      ['style', ['style']],
      ['font', ['bold', 'italic', 'underline', 'clear']],
      ['fontname', ['fontname']],
      ['fontsize', ['fontsize']],
      ['color', ['color']],
      ['para', ['ul', 'ol', 'paragraph']],
      ['table', ['table']],
      ['insert', ['link', 'picture']],
      ['view', ['fullscreen', 'codeview']],
    ],
  };
}

export async function initModalEditors(scopeEl = document) {
  const ok = await ensureSummernoteLoaded();
  if (!ok) {
    console.warn('Summernote failed to load.');
    return;
  }

  const $root = window.jQuery(scopeEl);

  // ✅ init any textarea[data-summernote="1"]
  $root.find('textarea[data-summernote="1"]').each(function () {
    const $el = window.jQuery(this);
    const h = Number($el.data('summernote-height') || 360);

    try {
      // destroy if already initialised
      if ($el.next('.note-editor').length) {
        $el.summernote('destroy');
      }
    } catch (_) {}

    $el.summernote(summernoteOptions(h));
  });
}

// optional: destroy editors (عشان ما يصير تداخل)
export function destroyModalEditors(scopeEl = document) {
  if (!window.jQuery?.fn?.summernote) return;
  const $root = window.jQuery(scopeEl);
  $root.find('textarea[data-summernote="1"]').each(function () {
    const $el = window.jQuery(this);
    try {
      if ($el.next('.note-editor').length) $el.summernote('destroy');
    } catch (_) {}
  });
}
