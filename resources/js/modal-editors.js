// resources/js/modal-editors.js
// Quill editor initializer for Bootstrap 5 modals (NO jQuery)
// الهدف: محرر غني داخل Ajax Modal بدون تعارضات + حفظ مضمون مع Ajax (FormData)

import Quill from 'quill';
import 'quill/dist/quill.snow.css';

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
  if (!form || form.dataset.quillHooked === '1') return;
  form.dataset.quillHooked = '1';

  // ✅ ضمان أخير قبل أي submit (حتى لو غير ajax)
  form.addEventListener('submit', () => {
    form.querySelectorAll('textarea[data-editor="quill"][data-quill-ready="1"]').forEach((t) => {
      const inst = t.__quillInstance;
      if (inst) t.value = inst.root.innerHTML;
    });
  }, true); // capture = true
}

async function buildOneTextarea(ta) {
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

  const height = Number(ta.getAttribute('data-editor-height') || ta.getAttribute('data-summernote-height') || 320);
  quill.root.style.minHeight = Math.max(200, height - 60) + 'px';

  // ✅ الأهم: تحديث textarea “لحظياً” مع كل تغيير (يحُل مشكلة عدم الحفظ مع Ajax)
  const sync = () => { ta.value = quill.root.innerHTML; };
  quill.on('text-change', sync);
  sync();

  // hook form (مرة واحدة)
  const form = ta.closest('form');
  hookFormOnce(form);

  // خزّن instance
  ta.__quillInstance = quill;
  ta.dataset.quillReady = '1';
  ta.setAttribute('data-quill-ready', '1');
}

export async function initModalEditors(scopeEl = document) {
  injectFixCssOnce();

  const scope = scopeEl instanceof Element ? scopeEl : document;

  const textareas = scope.querySelectorAll('textarea[data-editor="quill"]');
  if (!textareas.length) return;

  await waitFrames(2);

  for (const ta of textareas) {
    try {
      await buildOneTextarea(ta);
    } catch (e) {
      console.error('❌ Quill init failed for textarea:', ta, e);
    }
  }
}
