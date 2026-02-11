// resources/js/modal-editors.js
// Supports: Quill (legacy) + Summernote (Bootstrap 5)
// الهدف: محرر غني داخل Ajax Modal بدون تعارضات + حفظ مضمون مع Ajax (FormData)

import Quill from 'quill';
import 'quill/dist/quill.snow.css';

// ✅ jQuery (must be global BEFORE loading summernote plugin)
import $ from 'jquery';

// ✅ Summernote CSS
import 'summernote/dist/summernote-bs5.css';

// expose jQuery globally (some plugins expect it)
window.$ = window.jQuery = $;

/**
 * ✅ Bootstrap 5 → jQuery bridge
 * Summernote (حتى bs5 build) قد يستدعي $.fn.tooltip / $.fn.modal
 * وفي Bootstrap 5 هذه غير موجودة، فيسبب:
 *  Cannot read properties of undefined (reading 'apply')
 */
async function ensureBootstrapAndBridges() {
  // 1) تأكد bootstrap موجود عالميًا
  if (!window.bootstrap) {
    try {
      const bs = await import('bootstrap');
      window.bootstrap = bs;
    } catch (e) {
      console.warn('Bootstrap dynamic import failed', e);
    }
  }

  const bs = window.bootstrap;
  if (!bs) return;

  // 2) Tooltip bridge
  if (!$.fn.tooltip && bs.Tooltip) {
    $.fn.tooltip = function (configOrMethod, ...args) {
      return this.each(function () {
        const el = this;
        let inst = bs.Tooltip.getInstance(el);

        if (!inst) {
          // create instance
          inst = new bs.Tooltip(el, typeof configOrMethod === 'object' ? configOrMethod : {});
        }

        // call method if string
        if (typeof configOrMethod === 'string' && typeof inst[configOrMethod] === 'function') {
          inst[configOrMethod](...args);
        }
      });
    };
  }

  // 3) Modal bridge (sometimes summernote uses it)
  if (!$.fn.modal && bs.Modal) {
    $.fn.modal = function (configOrMethod, ...args) {
      return this.each(function () {
        const el = this;
        let inst = bs.Modal.getInstance(el);

        if (!inst) {
          inst = new bs.Modal(el, typeof configOrMethod === 'object' ? configOrMethod : {});
        }

        if (typeof configOrMethod === 'string' && typeof inst[configOrMethod] === 'function') {
          inst[configOrMethod](...args);
        }
      });
    };
  }

  // 4) Dropdown bridge (احتياط لبعض popovers)
  if (!$.fn.dropdown && bs.Dropdown) {
    $.fn.dropdown = function (configOrMethod, ...args) {
      return this.each(function () {
        const el = this;
        let inst = bs.Dropdown.getInstance(el);

        if (!inst) {
          inst = new bs.Dropdown(el, typeof configOrMethod === 'object' ? configOrMethod : {});
        }

        if (typeof configOrMethod === 'string' && typeof inst[configOrMethod] === 'function') {
          inst[configOrMethod](...args);
        }
      });
    };
  }
}

// ✅ Load summernote plugin AFTER window.jQuery is set + bridges ready
let __summernoteLoaded = false;
async function ensureSummernoteLoaded() {
  if (__summernoteLoaded) return;

  // مهم: جهّز bootstrap bridges قبل تحميل summernote
  await ensureBootstrapAndBridges();

  // IMPORTANT: plugin reads window.jQuery on load in many builds
  await import('summernote/dist/summernote-bs5');

  __summernoteLoaded = true;
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

    /* ✅ Summernote inside modal */
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

  // ✅ ضمان أخير قبل أي submit (حتى لو غير ajax)
  form.addEventListener(
    'submit',
    () => {
      // Quill -> textarea
      form
        .querySelectorAll('textarea[data-editor="quill"][data-quill-ready="1"]')
        .forEach((t) => {
          const inst = t.__quillInstance;
          if (inst) t.value = inst.root.innerHTML;
        });

      // Summernote -> hidden info input (لو موجود)
      const infoTa = form.querySelector('#infoEditor');
      const infoHidden = form.querySelector('#infoHidden');
      if (infoTa && infoHidden && $(infoTa).data('summernote')) {
        infoHidden.value = $(infoTa).summernote('code') || '';
      }
    },
    true
  );
}

/* =========================
   ✅ Quill (legacy)
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

  // اخفِ textarea (لكن يبقى name موجود للحفظ)
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

  const initialHtml = ta.value || '';
  quill.clipboard.dangerouslyPasteHTML(initialHtml);

  const height = Number(
    ta.getAttribute('data-editor-height') || ta.getAttribute('data-summernote-height') || 320
  );
  quill.root.style.minHeight = Math.max(200, height - 60) + 'px';

  const sync = () => {
    ta.value = quill.root.innerHTML;
  };
  quill.on('text-change', sync);
  sync();

  const form = ta.closest('form');
  hookFormOnce(form);

  ta.__quillInstance = quill;
  ta.dataset.quillReady = '1';
  ta.setAttribute('data-quill-ready', '1');
}

/* =========================
   ✅ Summernote (Bootstrap 5)
========================= */
async function buildOneSummernoteTextarea(ta) {
  if (!ta || !ta.isConnected) return;

  // prevent double init
  if (ta.dataset.summernoteReady === '1') return;

  const form = ta.closest('form');
  hookFormOnce(form);

  const height = Number(ta.getAttribute('data-summernote-height') || 320);
  const uploadUrl = ta.getAttribute('data-upload-url') || '';

  // ✅ Ensure plugin + bridges are loaded
  await ensureSummernoteLoaded();

  await waitFrames(1);

  // show textarea
  ta.classList.remove('d-none');
  ta.style.display = '';

  $(ta).summernote({
    height,
    toolbar: [
      ['style', ['style']],
      ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
      ['fontname', ['fontname']],
      ['fontsize', ['fontsize']],
      ['color', ['color']],
      ['para', ['ul', 'ol', 'paragraph']],
      ['table', ['table']],
      ['insert', ['link', 'picture', 'video']],
      ['view', ['fullscreen', 'codeview', 'help']],
    ],
    callbacks: {
      onInit: function () {
        // ✅ أول sync
        const hidden = form?.querySelector('#infoHidden');
        if (hidden) hidden.value = $(ta).summernote('code') || '';
      },
      onChange: function (contents) {
        const hidden = form?.querySelector('#infoHidden');
        if (hidden) hidden.value = contents || '';
      },
      onImageUpload: function (files) {
        if (!uploadUrl || !files || !files.length) return;

        const file = files[0];
        const fd = new FormData();
        fd.append('file', file);

        // CSRF
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
            if (url) $(ta).summernote('insertImage', url);
          })
          .catch((e) => console.warn('Summernote upload failed', e));
      },
    },
  });

  ta.dataset.summernoteReady = '1';
  ta.setAttribute('data-summernote-ready', '1');
}

/* =========================
   ✅ Public initializer
========================= */
export async function initModalEditors(scopeEl = document) {
  injectFixCssOnce();

  const scope = scopeEl instanceof Element ? scopeEl : document;

  // ✅ 1) Summernote
  const snTextareas = scope.querySelectorAll(
    'textarea[data-summernote="1"], textarea[data-editor="summernote"]'
  );
  if (snTextareas.length) {
    for (const ta of snTextareas) {
      try {
        await buildOneSummernoteTextarea(ta);
      } catch (e) {
        console.error('❌ Summernote init failed for textarea:', ta, e);
      }
    }
  }

  // ✅ 2) Quill legacy support
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