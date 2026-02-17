// resources/js/modal-editors.js
// Supports: Quill (legacy) + Summernote (lite)
// Best practice: init/destroy from ONE JS file, after modal shown.

import Quill from 'quill';
import 'quill/dist/quill.snow.css';

let $ = window.jQuery;

async function ensureGlobalJquery() {
  if (window.jQuery) {
    $ = window.jQuery;
    return $;
  }
  const mod = await import('jquery');
  $ = mod.default || mod;
  window.$ = window.jQuery = $;
  return $;
}

async function ensureBootstrapAndBridges() {
  await ensureGlobalJquery();

  if (!window.bootstrap) {
    try {
      const bs = await import('bootstrap');
      window.bootstrap = bs;
    } catch (e) {
      console.warn('Bootstrap dynamic import failed', e);
    }
  }

  const bs = window.bootstrap;
  if (!bs || !$) return;

  if (!$.fn.tooltip && bs.Tooltip) {
    $.fn.tooltip = function (configOrMethod, ...args) {
      return this.each(function () {
        const el = this;
        let inst = bs.Tooltip.getInstance(el);
        if (!inst) inst = new bs.Tooltip(el, typeof configOrMethod === 'object' ? configOrMethod : {});
        if (typeof configOrMethod === 'string' && typeof inst[configOrMethod] === 'function') {
          inst[configOrMethod](...args);
        }
      });
    };
  }

  if (!$.fn.modal && bs.Modal) {
    $.fn.modal = function (configOrMethod, ...args) {
      return this.each(function () {
        const el = this;
        let inst = bs.Modal.getInstance(el);
        if (!inst) inst = new bs.Modal(el, typeof configOrMethod === 'object' ? configOrMethod : {});
        if (typeof configOrMethod === 'string' && typeof inst[configOrMethod] === 'function') {
          inst[configOrMethod](...args);
        }
      });
    };
  }

  if (!$.fn.dropdown && bs.Dropdown) {
    $.fn.dropdown = function (configOrMethod, ...args) {
      return this.each(function () {
        const el = this;
        let inst = bs.Dropdown.getInstance(el);
        if (!inst) inst = new bs.Dropdown(el, typeof configOrMethod === 'object' ? configOrMethod : {});
        if (typeof configOrMethod === 'string' && typeof inst[configOrMethod] === 'function') {
          inst[configOrMethod](...args);
        }
      });
    };
  }
}

let __summernoteReady = false;
async function ensureSummernoteReady() {
  if (__summernoteReady) return;

  await ensureGlobalJquery();
  await ensureBootstrapAndBridges();

  // admin.js already loads summernote-lite normally
  if (typeof window.jQuery?.fn?.summernote === 'function') {
    __summernoteReady = true;
    return;
  }

  // fallback (once)
  try {
    await import('summernote/dist/summernote-lite.min.js');
    __summernoteReady = true;
  } catch (e) {
    console.error('Failed to load summernote-lite fallback', e);
  }
}

function injectFixCssOnce() {
  if (document.getElementById('orders-quill-fix')) return;

  const st = document.createElement('style');
  st.id = 'orders-quill-fix';
  st.textContent = `
    .ql-toolbar.ql-snow { position: sticky; top: 0; z-index: 1060; background: #fff; }
    .ql-container.ql-snow { border-radius: .375rem; }
    .ql-editor { min-height: 260px; }
    .modal-body .ql-toolbar { z-index: 1060; }
    .ql-snow .ql-tooltip { z-index: 20000 !important; }
    .quill-wrap { border: 1px solid #dee2e6; border-radius: .375rem; overflow: hidden; }

    /* Summernote inside modal */
    .note-editor.note-frame { border-radius: .375rem; }
    .note-editor .note-toolbar { position: sticky; top: 0; z-index: 1060; background: #fff; }
    .note-modal { z-index: 21050 !important; }
    .note-popover { z-index: 21060 !important; }
  `;
  document.head.appendChild(st);
}

function nextFrame() {
  return new Promise((r) => requestAnimationFrame(() => r()));
}
async function waitFrames(n = 2) {
  for (let i = 0; i < n; i++) await nextFrame();
}

function hookFormOnce(form) {
  if (!form || form.dataset.richEditorsHooked === '1') return;
  form.dataset.richEditorsHooked = '1';

  form.addEventListener(
    'submit',
    () => {
      // Quill -> textarea
      form.querySelectorAll('textarea[data-editor="quill"][data-quill-ready="1"]').forEach((t) => {
        const inst = t.__quillInstance;
        if (inst) t.value = inst.root.innerHTML;
      });

      // Summernote -> hidden
      const infoTa = form.querySelector('#infoEditor');
      const infoHidden = form.querySelector('#infoHidden');
      if (infoTa && infoHidden && window.jQuery && window.jQuery(infoTa).data('summernote')) {
        infoHidden.value = window.jQuery(infoTa).summernote('code') || '';
      }
    },
    true
  );
}

/* =========================
   Quill (legacy)
========================= */
async function buildOneQuillTextarea(ta) {
  if (ta.dataset.quillReady === '1') return;
  if (!ta.isConnected) return;

  const wrap = document.createElement('div');
  wrap.className = 'quill-wrap';

  const editorDiv = document.createElement('div');
  editorDiv.className = 'quill-editor';
  wrap.appendChild(editorDiv);

  ta.insertAdjacentElement('afterend', wrap);
  ta.style.display = 'none';

  await waitFrames(2);

  const toolbar = [
    [{ header: [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ align: [] }],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['blockquote', 'code-block'],
    ['link', 'image'],
    ['clean'],
  ];

  const quill = new Quill(editorDiv, {
    theme: 'snow',
    modules: { toolbar },
  });

  quill.clipboard.dangerouslyPasteHTML(ta.value || '');

  const height = Number(ta.getAttribute('data-editor-height') || ta.getAttribute('data-summernote-height') || 320);
  quill.root.style.minHeight = Math.max(200, height - 60) + 'px';

  const sync = () => (ta.value = quill.root.innerHTML);
  quill.on('text-change', sync);
  sync();

  hookFormOnce(ta.closest('form'));

  ta.__quillInstance = quill;
  ta.dataset.quillReady = '1';
  ta.setAttribute('data-quill-ready', '1');
}

/* =========================
   Summernote (toolbar like other project)
========================= */
export function destroySummernoteIn(scopeEl = document) {
  const scope = scopeEl instanceof Element ? scopeEl : document;
  const tas = scope.querySelectorAll('textarea[data-summernote="1"], textarea[data-editor="summernote"]');

  const $w = window.jQuery;
  if (!$w || !$w.fn || typeof $w.fn.summernote !== 'function') return;

  tas.forEach((ta) => {
    try {
      const $ta = $w(ta);
      if ($ta.data('summernote') || $ta.next('.note-editor').length) {
        $ta.summernote('destroy');
      }
    } catch (_) {}
  });
}

async function buildOneSummernoteTextarea(ta) {
  if (!ta || !ta.isConnected) return;

  await ensureSummernoteReady();
  await ensureGlobalJquery();

  if (typeof window.jQuery?.fn?.summernote !== 'function') {
    console.error('Summernote is not available on current window.jQuery.');
    return;
  }

  // IMPORTANT: if reopened / double init → destroy then init
  try {
    const $ta = window.jQuery(ta);
    if ($ta.data('summernote') || $ta.next('.note-editor').length) $ta.summernote('destroy');
  } catch (_) {}

  hookFormOnce(ta.closest('form'));

  const height = Number(ta.getAttribute('data-summernote-height') || 320);
  const uploadUrl = ta.getAttribute('data-upload-url') || '';

  await waitFrames(1);

  ta.classList.remove('d-none');
  ta.style.display = '';

  window.jQuery(ta).summernote({
    height,
    toolbar: [
      ['style', ['style']],
      ['fontsize', ['fontsize']],
      ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
      ['fontname', ['fontname']],
      ['color', ['color']],
      ['para', ['ul', 'ol', 'paragraph']],
      ['table', ['table']],
      ['insert', ['link', 'picture', 'video']],
      ['view', ['fullscreen', 'codeview', 'help']],
    ],
    callbacks: {
      onInit: function () {
        const form = ta.closest('form');
        const hidden = form?.querySelector('#infoHidden');
        if (hidden) hidden.value = window.jQuery(ta).summernote('code') || '';
      },
      onChange: function (contents) {
        const form = ta.closest('form');
        const hidden = form?.querySelector('#infoHidden');
        if (hidden) hidden.value = contents || '';
      },
      onImageUpload: function (files) {
        if (!uploadUrl || !files || !files.length) return;

        const fd = new FormData();
        fd.append('file', files[0]);

        const form = ta.closest('form');
        const token =
          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
          form?.querySelector('input[name="_token"]')?.value ||
          '';

        fetch(uploadUrl, {
          method: 'POST',
          headers: token ? { 'X-CSRF-TOKEN': token } : {},
          body: fd,
        })
          .then((r) => r.json())
          .then((data) => {
            const url = data?.url || data?.path || '';
            if (url) window.jQuery(ta).summernote('insertImage', url);
          })
          .catch((e) => console.warn('Summernote upload failed', e));
      },
    },
  });
}

/* =========================
   Public initializer
========================= */
export async function initModalEditors(scopeEl = document) {
  injectFixCssOnce();

  const scope = scopeEl instanceof Element ? scopeEl : document;

  // Summernote
  const snTextareas = scope.querySelectorAll('textarea[data-summernote="1"], textarea[data-editor="summernote"]');
  for (const ta of snTextareas) {
    try {
      await buildOneSummernoteTextarea(ta);
    } catch (e) {
      console.error('❌ Summernote init failed for textarea:', ta, e);
    }
  }

  // Quill legacy
  const quillTextareas = scope.querySelectorAll('textarea[data-editor="quill"]');
  if (!quillTextareas.length) return;

  await waitFrames(2);

  for (const ta of quillTextareas) {
    try {
      await buildOneQuillTextarea(ta);
    } catch (e) {
      console.error('❌ Quill init failed for textarea:', ta, e);
    }
  }
}
