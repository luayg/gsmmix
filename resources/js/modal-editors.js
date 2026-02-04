// resources/js/modal-editors.js
// TinyMCE initializer (no jQuery, no Bootstrap plugin dependency)

function loadScriptOnce(id, src) {
  return new Promise((resolve, reject) => {
    if (document.getElementById(id)) return resolve();
    const s = document.createElement('script');
    s.id = id;
    s.src = src;
    s.async = true;
    s.onload = () => resolve();
    s.onerror = (e) => reject(e);
    document.head.appendChild(s);
  });
}

async function ensureTinyMce() {
  if (window.tinymce) return;

  // TinyMCE CDN
  await loadScriptOnce(
    'tinymce-cdn',
    'https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js'
  );

  if (!window.tinymce) {
    throw new Error('TinyMCE failed to load.');
  }
}

function uniqueId(prefix = 'ed') {
  return prefix + '-' + Math.random().toString(16).slice(2) + Date.now();
}

export async function initModalEditors(scopeEl = document) {
  const scope = scopeEl instanceof Element ? scopeEl : document;
  const textareas = scope.querySelectorAll('textarea[data-editor="tinymce"]');
  if (!textareas.length) return;

  await ensureTinyMce();

  // مهم: إذا كان هناك محررات قديمة داخل نفس المودال، امسحها أولاً
  for (const ta of textareas) {
    if (!ta.id) ta.id = uniqueId('reply');
    const existing = window.tinymce.get(ta.id);
    if (existing) {
      existing.remove();
    }
  }

  for (const ta of textareas) {
    const height = Number(ta.getAttribute('data-editor-height') || 320);

    await window.tinymce.init({
      target: ta,
      height,
      menubar: false,
      branding: false,
      promotion: false,
      convert_urls: false,
      relative_urls: false,

      plugins: [
        'lists', 'link', 'image', 'table', 'code', 'fullscreen'
      ],
      toolbar:
        'undo redo | bold italic underline strikethrough | ' +
        'fontsize | forecolor backcolor | ' +
        'alignleft aligncenter alignright alignjustify | ' +
        'bullist numlist | table | link image | fullscreen code',

      // مهم داخل المودال: اجعل الـ dialog فوق كل شيء
      dialog_type: 'modal',

      setup: (editor) => {
        // عند Submit لازم نرجّع المحتوى للـ textarea
        const form = ta.closest('form');
        if (form && !form.dataset.tinymceBound) {
          form.dataset.tinymceBound = '1';
          form.addEventListener('submit', () => {
            window.tinymce.triggerSave();
          });
        }
      }
    });
  }
}
