// resources/js/modal-editors.js

/* global window, document */

function getJQ() {
  return window.jQuery || window.$ || null;
}

function normalizeUrl(url) {
  try {
    if (!url) return '';
    return String(url);
  } catch (e) {
    return '';
  }
}

function buildSummernoteOptions(textarea) {
  const h = Number(textarea.getAttribute('data-summernote-height') || 320);
  const uploadUrl = normalizeUrl(textarea.getAttribute('data-upload-url'));

  // Toolbar واسعة (مثل اللي تحبه)
  return {
    height: Number.isFinite(h) ? h : 320,
    dialogsInBody: true,
    placeholder: textarea.getAttribute('placeholder') || '',
    toolbar: [
      ['style', ['style']],
      ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
      ['fontname', ['fontname']],
      ['fontsize', ['fontsize']],
      ['color', ['color']],
      ['para', ['ul', 'ol', 'paragraph']],
      ['table', ['table']],
      ['insert', ['link', 'picture', 'video', 'hr']],
      ['view', ['fullscreen', 'codeview', 'help']]
    ],
    callbacks: {
      onImageUpload: function (files) {
        if (!uploadUrl) return;

        const $ = getJQ();
        if (!$) return;

        const $editor = $(textarea);
        const form = new FormData();
        form.append('file', files[0]);

        // Laravel CSRF (إن وجد)
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (token) form.append('_token', token);

        fetch(uploadUrl, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: form
        })
          .then(r => r.json().catch(() => null))
          .then(json => {
            const url = json?.url || json?.data?.url || json?.location;
            if (url) {
              $editor.summernote('insertImage', url);
            }
          })
          .catch(() => {});
      }
    }
  };
}

function initSummernoteInScope(scope) {
  const $ = getJQ();
  if (!$) return;

  // Plugin check
  if (!$.fn || typeof $.fn.summernote !== 'function') {
    // لا نكسر الصفحة — فقط تحذير مرة واحدة
    if (!window.__gsmmixWarnedSummernoteMissing) {
      window.__gsmmixWarnedSummernoteMissing = true;
      console.warn('Summernote plugin not found. Please ensure it is loaded in admin bundle.');
    }
    return;
  }

  const root = scope || document;
  const textareas = Array.from(root.querySelectorAll('textarea[data-editor="summernote"], textarea[data-summernote="1"]'));

  textareas.forEach((ta) => {
    try {
      // prevent double init
      if (ta.dataset.snInit === '1') return;

      // ensure element exists in DOM
      if (!ta || !ta.parentNode) return;

      const opts = buildSummernoteOptions(ta);
      $(ta).summernote(opts);

      ta.dataset.snInit = '1';
    } catch (e) {
      console.warn('Summernote init failed for textarea:', ta, e);
    }
  });
}

function syncSummernoteToHidden(scope) {
  const $ = getJQ();
  if (!$ || !$.fn || typeof $.fn.summernote !== 'function') return;

  const root = scope || document;
  const form = root.querySelector('form');
  if (!form) return;

  const ta = root.querySelector('#infoEditor');
  const hidden = root.querySelector('#infoHidden');
  if (!ta || !hidden) return;

  try {
    // if initialized
    if (ta.dataset.snInit === '1') {
      hidden.value = $(ta).summernote('code') || '';
    } else {
      hidden.value = ta.value || '';
    }
  } catch (e) {
    hidden.value = ta.value || '';
  }
}

export async function initModalEditors(scope) {
  const root = scope || document;

  // Summernote
  initSummernoteInScope(root);

  // Keep helper available globally for older code
  window.__gsmmixSyncInfoToHidden = function (s) {
    syncSummernoteToHidden(s || root);
  };

  return true;
}

// expose globally (required by service-modal script)
window.initModalEditors = initModalEditors;

// Global listeners: when any modal form submits, sync info editor
document.addEventListener('submit', function (ev) {
  const form = ev.target;
  if (!form) return;

  const modal = form.closest('.modal') || document;
  syncSummernoteToHidden(modal);
}, true);
