// resources/js/modal-editors.js
// Summernote (BS5) initializer عبر Vite (بدون CDN) لتفادي تعارض jQuery instances

import $ from 'jquery';

// اربط jQuery على window (فقط إذا غير موجود) حتى summernote يلتقطه
if (!window.jQuery) window.jQuery = $;
if (!window.$) window.$ = $;

// استيراد summernote من node_modules (نفس النسخة المثبتة بالمشروع)
import 'summernote/dist/summernote-bs5.min.js';
import 'summernote/dist/summernote-bs5.min.css';

export async function initModalEditors(scopeEl = document) {
  const scope = scopeEl instanceof Element ? scopeEl : document;

  const textareas = scope.querySelectorAll('textarea[data-summernote="1"]');
  if (!textareas.length) return;

  // ✅ Fix z-index داخل المودال
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

    // امنع double-init
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
