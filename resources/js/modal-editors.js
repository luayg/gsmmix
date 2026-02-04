// C:\xampp\htdocs\gsmmix\resources\js\modal-editors.js
// Quill editor initializer for Bootstrap 5 modals (NO jQuery, NO Summernote)

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

/**
 * ✅ مزامنة كل محولات Quill داخل form قبل الإرسال (مهم جدًا مع Ajax)
 */
function syncQuillInForm(form) {
  if (!form) return;

  form.querySelectorAll('textarea[data-editor="quill"][data-quill-ready="1"]').forEach((t) => {
    const inst = t.__quillInstance;
    if (inst) {
      // خذ HTML الحالي من Quill وضعه في textarea ليُرسل للسيرفر
      t.value = inst.root.innerHTML;
    }
  });
}

/**
 * ✅ نثبت Hook عالمي مرة واحدة (Capture Phase)
 * هذا يضمن أن textarea تتحدث قبل أي Ajax handler آخر (مثل admin.js)
 */
function ensureGlobalSubmitHookOnce() {
  if (window.__QUILL_GLOBAL_SUBMIT_HOOKED__) return;
  window.__QUILL_GLOBAL_SUBMIT_HOOKED__ = true;

  document.addEventListener(
    'submit',
    (e) => {
      const form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      syncQuillInForm(form);
    },
    true // ✅ capture
  );

  // بعض أكواد Ajax تعتمد على click زر submit بدل submit event
  document.addEventListener(
    'click',
    (e) => {
      const btn = e.target.closest('button[type="submit"], input[type="submit"]');
      if (!btn) return;
      const form = btn.closest('form');
      if (form) syncQuillInForm(form);
    },
    true
  );
}

/**
 * ✅ تحويل badge bootstrap إلى ألوان داخل Quill بدل ما تضيع الكلاسات
 * لأن Quill غالبًا لا يحافظ على class="badge bg-success"
 */
function bootstrapBadgeToQuillColors(node) {
  if (!node?.classList) return null;
  if (!node.classList.contains('badge')) return null;

  const cls = Array.from(node.classList);

  // ألوان Bootstrap تقريبية
  const map = {
    'bg-success': { background: '#198754', color: '#ffffff' },
    'bg-danger': { background: '#dc3545', color: '#ffffff' },
    'bg-warning': { background: '#ffc107', color: '#000000' },
    'bg-info': { background: '#0dcaf0', color: '#000000' },
    'bg-secondary': { background: '#6c757d', color: '#ffffff' },
    'bg-dark': { background: '#212529', color: '#ffffff' },
    'bg-primary': { background: '#0d6efd', color: '#ffffff' },
  };

  for (const k of Object.keys(map)) {
    if (cls.includes(k)) return map[k];
  }

  // badge بدون لون واضح
  return { background: '#6c757d', color: '#ffffff' };
}

/**
 * يبني Quill بدل textarea
 */
async function buildOneTextarea(ta) {
  // منع إعادة التهيئة
  if (ta.dataset.quillReady === '1') return;
  if (!ta.isConnected) return;

  const wrap = document.createElement('div');
  wrap.className = 'quill-wrap';

  const editorDiv = document.createElement('div');
  editorDiv.className = 'quill-editor';
  wrap.appendChild(editorDiv);

  // ضع الـ wrapper بعد textarea
  ta.insertAdjacentElement('afterend', wrap);

  // اخفِ textarea (لكن تبقى موجودة للإرسال)
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
    modules: {
      toolbar,
    },
  });

  /**
   * ✅ Matcher: لو جاء HTML فيه span.badge bg-success... نخليه ألوان داخل Quill
   */
  quill.clipboard.addMatcher('SPAN', (node, delta) => {
    const colors = bootstrapBadgeToQuillColors(node);
    if (!colors) return delta;

    // طبق الألوان على كل النص داخل الـ badge
    delta.ops = (delta.ops || []).map((op) => {
      if (typeof op.insert === 'string' && op.insert.trim() !== '') {
        op.attributes = op.attributes || {};
        op.attributes.color = colors.color;
        op.attributes.background = colors.background;
        op.attributes.bold = true;
      }
      return op;
    });
    return delta;
  });

  // حمّل HTML من textarea
  const initialHtml = ta.value || '';
  quill.clipboard.dangerouslyPasteHTML(initialHtml);

  const height = Number(ta.getAttribute('data-editor-height') || ta.getAttribute('data-summernote-height') || 320);
  quill.root.style.minHeight = Math.max(200, height - 60) + 'px';

  // خزّن instance
  ta.__quillInstance = quill;
  ta.dataset.quillReady = '1';
  ta.setAttribute('data-quill-ready', '1');
}

/**
 * init for scope
 */
export async function initModalEditors(scopeEl = document) {
  injectFixCssOnce();
  ensureGlobalSubmitHookOnce();

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
