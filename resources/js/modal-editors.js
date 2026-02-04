// resources/js/modal-editors.js
// Quill editor initializer for Bootstrap 5 modals
// الهدف: محرر غني داخل Ajax Modal بدون تعارضات + ضمان حفظ القيمة داخل textarea قبل أي Ajax submit

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

// يحول textarea إلى Quill + يضمن مزامنة القيمة للحفظ
async function buildOneTextarea(ta) {
  if (ta.dataset.quillReady === '1') return;
  if (!ta.isConnected) return;

  // wrapper
  const wrap = document.createElement('div');
  wrap.className = 'quill-wrap';

  const editorDiv = document.createElement('div');
  editorDiv.className = 'quill-editor';
  wrap.appendChild(editorDiv);

  // ضع wrapper بعد textarea
  ta.insertAdjacentElement('afterend', wrap);

  // اخفِ textarea لكن اتركه في الفورم للحفظ
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

  // حمّل HTML من textarea
  const initialHtml = ta.value || '';
  quill.clipboard.dangerouslyPasteHTML(initialHtml);

  // حدّد ارتفاع
  const height = Number(
    ta.getAttribute('data-editor-height') ||
    ta.getAttribute('data-summernote-height') ||
    320
  );
  quill.root.style.minHeight = Math.max(200, height - 60) + 'px';

  // ✅ أهم سطرين لحل مشكلة عدم الحفظ:
  // 1) مزامنة textarea في كل تغيير (حتى لو Ajax يمنع submit)
  const syncToTextarea = () => { ta.value = quill.root.innerHTML; };
  quill.on('text-change', syncToTextarea);
  syncToTextarea();

  // 2) مزامنة قبل submit في طور الالتقاط capture (قبل أي listener آخر)
  const form = ta.closest('form');
  if (form && !form.dataset.quillHooked) {
    form.dataset.quillHooked = '1';

    form.addEventListener('submit', () => {
      form.querySelectorAll('textarea[data-editor="quill"][data-quill-ready="1"]').forEach((t) => {
        const inst = t.__quillInstance;
        if (inst) t.value = inst.root.innerHTML;
      });
    }, true); // ✅ capture = true
  }

  // خزّن instance
  ta.__quillInstance = quill;
  ta.dataset.quillReady = '1';
  ta.setAttribute('data-quill-ready', '1');
}

export async function initModalEditors(scopeEl = document) {
  injectFixCssOnce();

  const scope = scopeEl instanceof Element ? scopeEl : document;

  // نشتغل فقط على textareas التي نحددها
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
