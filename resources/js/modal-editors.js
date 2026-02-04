// resources/js/modal-editors.js
// IMPORTANT: يعتمد على window.jQuery الموجود من صفحة الأدمن (بدون استيراد jquery داخل Vite)

function loadCssOnce(id, href) {
  if (document.getElementById(id)) return;
  const l = document.createElement('link');
  l.id = id;
  l.rel = 'stylesheet';
  l.href = href;
  document.head.appendChild(l);
}

function loadScriptOnce(id, src) {
  return new Promise((resolve, reject) => {
    if (document.getElementById(id)) return resolve();
    const s = document.createElement('script');
    s.id = id;
    s.src = src;
    s.async = false;
    s.onload = () => resolve();
    s.onerror = (e) => reject(e);
    document.body.appendChild(s);
  });
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

async function ensureSummernoteOnPageJquery() {
  const $ = window.jQuery;
  if (!$) throw new Error('window.jQuery is missing (admin layout must provide it).');

  // إذا Summernote موجود بالفعل على نفس jQuery
  if ($.fn && typeof $.fn.summernote === 'function') return;

  // حمّل Summernote BS5 (FULL)
  loadCssOnce(
    'orders-sn-bs5-css',
    'https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.css'
  );

  await loadScriptOnce(
    'orders-sn-bs5-js',
    'https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.js'
  );

  // تأكيد أنه ركب على نفس jQuery
  if (!$.fn || typeof $.fn.summernote !== 'function') {
    throw new Error('Summernote loaded but NOT attached to window.jQuery (jQuery duplication/conflict).');
  }
}

function waitFrames(n = 2) {
  return new Promise((resolve) => {
    const step = () => {
      n -= 1;
      if (n <= 0) return resolve();
      requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  });
}

async function buildOneTextarea(ta) {
  const $ = window.jQuery;
  const $ta = $(ta);

  // تنظيف أي بقايا قديمة بدون استدعاء destroy إذا كان يسبب مشاكل
  try {
    if ($ta.next('.note-editor').length) {
      $ta.next('.note-editor').remove();
    }
    // امسح بيانات قديمة
    $ta.removeData('summernote');
  } catch (_) {}

  const height = Number(ta.getAttribute('data-summernote-height') || 320);

  // حاول init + لو فشل اطبع الخطأ الحقيقي
  try {
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
  } catch (e) {
    console.error('❌ Summernote init threw error:', e);
    throw e;
  }

  // انتظر رسم DOM
  await waitFrames(3);

  // تأكد أنه انبنى
  const editor = ta.parentElement?.querySelector('.note-editor');
  if (!editor) {
    console.warn('⚠️ Summernote did not build editor for textarea:', ta);
  }
}

export async function initModalEditors(scopeEl = document) {
  const scope = scopeEl instanceof Element ? scopeEl : document;

  const textareas = scope.querySelectorAll('textarea[data-summernote="1"]');
  if (!textareas.length) return;

  await ensureSummernoteOnPageJquery();
  injectFixCssOnce();

  // ملاحظة مهمة: لا تحاول init قبل ما يكون المودال ظاهر/مرسوم
  await waitFrames(2);

  for (const ta of textareas) {
    await buildOneTextarea(ta);
  }
}
