// resources/js/modal-editors.js
// TinyMCE loader + initializer (works inside Bootstrap 5 modal)
// - No jQuery dependency
// - Adds color buttons
// - Provides syncEditorsBeforeSubmit() so Reply is saved correctly

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

function uniqueId(prefix = 'rt') {
  return `${prefix}_${Math.random().toString(16).slice(2)}_${Date.now()}`;
}

async function ensureTinyMce() {
  // already loaded
  if (window.tinymce && typeof window.tinymce.init === 'function') return;

  // ✅ TinyMCE CDN (stable)
  // لو عندك API KEY ضعها بدل no-api-key
  await loadScriptOnce(
    'orders-tinymce-js',
    'https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js'
  );

  if (!window.tinymce) {
    throw new Error('TinyMCE loaded but window.tinymce is missing.');
  }
}

function buildSelectorForTextareas(textareas) {
  // Give each textarea an id and build selector by ids (safe inside modal injections)
  const ids = [];
  for (const ta of textareas) {
    if (!ta.id) ta.id = uniqueId('reply');
    ids.push(`#${CSS.escape(ta.id)}`);
  }
  return ids.join(',');
}

/**
 * Call this BEFORE submitting any form that contains editors
 * so textarea values get updated.
 */
export function syncEditorsBeforeSubmit() {
  try {
    if (window.tinymce?.triggerSave) {
      window.tinymce.triggerSave(); // ✅ writes editor content back into textarea
    }
  } catch (e) {
    console.warn('tinymce.triggerSave() failed:', e);
  }
}

export async function initModalEditors(scopeEl = document) {
  const scope = scopeEl instanceof Element ? scopeEl : document;

  // We keep your same attribute to avoid editing blade:
  const textareas = scope.querySelectorAll('textarea[data-summernote="1"]');
  if (!textareas.length) return;

  await ensureTinyMce();

  // Remove any previous instances for these textareas (important when modal reopens)
  for (const ta of textareas) {
    if (ta.id && window.tinymce?.get(ta.id)) {
      try { window.tinymce.get(ta.id).remove(); } catch (_) {}
    }
  }

  const selector = buildSelectorForTextareas(textareas);

  await window.tinymce.init({
    selector,

    // ✅ UI inside modal
    menubar: false,
    branding: false,
    height: 320,

    // ✅ Plugins you need (table + code + fullscreen + lists + link + colors)
    plugins: [
      'lists', 'link', 'table', 'code', 'fullscreen',
      'autoresize', 'charmap', 'searchreplace', 'visualblocks',
      'wordcount'
    ],

    // ✅ Toolbar WITH colors
    toolbar:
      'undo redo | blocks | bold italic underline strikethrough | ' +
      'forecolor backcolor | alignleft aligncenter alignright alignjustify | ' +
      'bullist numlist | table link | fullscreen code',

    // Better inside Bootstrap modal
    // puts dropdowns/menus inside body so they don't get clipped
    fixed_toolbar_container: false,

    // Keep HTML as-is (you already store HTML tables/images)
    valid_elements: '*[*]',
    extended_valid_elements: '*[*]',

    // Avoid content CSS surprises
    content_style: `
      body { font-family: Arial, sans-serif; font-size: 14px; }
      table { border-collapse: collapse; width: 100%; }
      table, td, th { border: 1px solid #000; }
      td, th { padding: 6px; }
      img { max-width: 100%; height: auto; }
    `,

    setup: (editor) => {
      editor.on('init', () => {
        // optional: console.log('TinyMCE ready:', editor.id);
      });
    }
  });
}
