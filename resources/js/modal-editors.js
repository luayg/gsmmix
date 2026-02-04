// resources/js/modal-editors.js
// Quill editor initializer for Bootstrap 5 modals (NO jQuery, NO Summernote)
// الهدف: محرر غني داخل Ajax Modal بدون تعارضات

import Quill from 'quill';
import 'quill/dist/quill.snow.css';

function injectFixCssOnce() {
  if (document.getElementById('orders-quill-fix')) return;

  const st = document.createElement('style');
  st.id = 'orders-quill-fix';
  st.textContent = `
    /* Quill inside Bootstrap modal: ensure toolbar + popups above modal */
    .ql-toolbar.ql-snow { position: sticky; top: 0; z-index: 1060; background: #fff; }
    .ql-container.ql-snow { border-radius: .375rem; }
    .ql-editor { min-height: 260px; }

    /* If modal body has overflow, sticky toolbar stays visible */
    .modal-body .ql-toolbar { z-index: 1060; }

    /* Quill dropdowns */
    .ql-snow .ql-tooltip { z-index: 20000 !important; }

    /* Keep editor width correct */
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

/**
 * يبني Quill بدل textarea
 * - نخفي textarea (لكن نترك name كما هو)
 * - ننشئ div editor ونملأه بـ HTML من textarea.value
 * - عند submit نعيد html إلى textarea.value
 */
async function buildOneTextarea(ta) {
  // منع إعادة التهيئة
  if (ta.dataset.quillReady === '1') return;

  // لازم يكون داخل DOM
  if (!ta.isConnected) return;

  // أنشئ wrapper
  const wrap = document.createElement('div');
  wrap.className = 'quill-wrap';

  const editorDiv = document.createElement('div');
  editorDiv.className = 'quill-editor';
  wrap.appendChild(editorDiv);

  // ضع الـ wrapper بعد textarea
  ta.insertAdjacentElement('afterend', wrap);

  // اخفِ textarea
  ta.style.display = 'none';

  // انتظر رندر DOM (خصوصًا داخل modal)
  await waitFrames(2);

  // Toolbar configuration
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
    modules: {
      toolbar,
      // clipboard: { matchVisual: false }, // لو احتجت
    },
  });

  // حمّل HTML من textarea (مهم: textarea يحفظ HTML كنص)
  const initialHtml = ta.value || '';
  quill.clipboard.dangerouslyPasteHTML(initialHtml);

  // حدّد ارتفاع لو موجود
  const height = Number(ta.getAttribute('data-editor-height') || ta.getAttribute('data-summernote-height') || 320);
  quill.root.style.minHeight = Math.max(200, height - 60) + 'px';

  // عند إرسال الفورم: ارجع HTML للـ textarea
  const form = ta.closest('form');
  if (form && !form.dataset.quillHooked) {
    form.dataset.quillHooked = '1';
    form.addEventListener('submit', () => {
      // حدّث كل textareas المحولة داخل نفس الفورم
      form.querySelectorAll('textarea[data-editor="quill"][data-quill-ready="1"]').forEach((t) => {
        const inst = t.__quillInstance;
        if (inst) t.value = inst.root.innerHTML;
      });
    });
  }

  // خزّن instance
  ta.__quillInstance = quill;
  ta.dataset.quillReady = '1';
  ta.setAttribute('data-quill-ready', '1');
}

export async function initModalEditors(scopeEl = document) {
  injectFixCssOnce();

  const scope = scopeEl instanceof Element ? scopeEl : document;

  // ✅ نشتغل فقط على textareas التي نحددها
  const textareas = scope.querySelectorAll('textarea[data-editor="quill"]');
  if (!textareas.length) return;

  // انتظر قليلًا حتى Bootstrap modal/DOM يستقر
  await waitFrames(2);

  for (const ta of textareas) {
    try {
      await buildOneTextarea(ta);
    } catch (e) {
      console.error('❌ Quill init failed for textarea:', ta, e);
    }
  }
}
