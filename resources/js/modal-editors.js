// resources/js/modal-editors.js

import $ from 'jquery';

let __summernoteLoaded = false;

async function ensureSummernoteLoaded() {
  if (__summernoteLoaded) return;
  if (!window.jQuery) window.jQuery = $;
  if (!window.$) window.$ = $;

  await import('summernote/dist/summernote-bs5.min.js');
  await import('summernote/dist/summernote-bs5.min.css');

  __summernoteLoaded = true;
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

export async function initModalEditors(scopeEl = document) {
  const scope = scopeEl instanceof Element ? scopeEl : document;
  const textareas = scope.querySelectorAll('textarea[data-summernote="1"]');
  if (!textareas.length) return;

  await ensureSummernoteLoaded();
  injectFixCssOnce();

  if (!window.jQuery?.fn?.summernote) {
    console.error('❌ Summernote still not attached to window.jQuery');
    return;
  }

  for (const ta of textareas) {
    const $ta = window.jQuery(ta);

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

    // ✅ التعديل: الانتظار قليلاً قبل التحقق من وجود المحرر في الـ DOM
    setTimeout(() => {
      const parent = ta.parentElement;
      const editor = parent ? parent.querySelector('.note-editor') : null;
      if (!editor) {
        console.warn('⚠️ Summernote did not build editor for textarea (Retrying...):', ta);
        $ta.summernote(); // محاولة أخيرة في حال الفشل
      }
    }, 150);
  }
}