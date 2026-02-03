// resources/js/modal-editors.js

const loadCssOnce = (id, href) => {
  if (document.getElementById(id)) return;
  const l = document.createElement('link');
  l.id = id;
  l.rel = 'stylesheet';
  l.href = href;
  document.head.appendChild(l);
};

const loadScriptOnce = (id, src) => new Promise((resolve, reject) => {
  if (document.getElementById(id)) return resolve();
  const s = document.createElement('script');
  s.id = id;
  s.src = src;
  s.async = false;
  s.onload = resolve;
  s.onerror = reject;
  document.body.appendChild(s);
});

async function ensureSummernote() {
  // Summernote CSS
  loadCssOnce('sn-css', 'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css');

  // إذا موجود خلاص
  if (window.jQuery && window.jQuery.fn && window.jQuery.fn.summernote) return true;

  // jQuery
  if (!window.jQuery) {
    await loadScriptOnce('jq-cdn', 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js');
    window.$ = window.jQuery;
  }

  // Summernote JS
  await loadScriptOnce('sn-cdn', 'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js');

  return !!(window.jQuery && window.jQuery.fn && window.jQuery.fn.summernote);
}

function initSummernoteIn(container) {
  const root = container instanceof Element ? container : document;

  const areas = root.querySelectorAll('textarea[data-summernote="1"]');
  if (!areas.length) return;

  window.jQuery(areas).each(function () {
    const $t = window.jQuery(this);

    // already initialized
    if ($t.next('.note-editor').length) return;

    const h = Number($t.attr('data-summernote-height') || 360);

    $t.summernote({
      height: h,
      focus: false,
      toolbar: [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['fontsize', ['fontsize']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['height', ['height']],
        ['insert', ['link', 'picture', 'table', 'hr']],
        ['view', ['codeview']]
      ],
      fontSizes: ['8','9','10','11','12','14','16','18','20','24','28','32','36','48'],
      callbacks: {
        // إدراج صورة Base64
        onImageUpload: function (files) {
          for (const f of files) {
            const reader = new FileReader();
            reader.onload = (e) => $t.summernote('insertImage', e.target.result);
            reader.readAsDataURL(f);
          }
        }
      }
    });
  });
}

// هذه الدالة هي اللي بنستدعيها من admin.js بعد حقن المودال
export async function initModalEditors(container) {
  const ok = await ensureSummernote();
  if (!ok) return;
  initSummernoteIn(container);
}
