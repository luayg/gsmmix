// resources/js/modal-editors.js
// Isolated editor loader for Orders Edit modal ONLY.

const CDN = {
  jquery: 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
  summernoteCss: 'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css',
  summernoteJs: 'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js',
};

// ---- helpers (load once)
function loadCssOnce(id, href) {
  if (document.getElementById(id)) return;
  const link = document.createElement('link');
  link.id = id;
  link.rel = 'stylesheet';
  link.href = href;
  document.head.appendChild(link);
}

function loadScriptOnce(id, src) {
  return new Promise((resolve, reject) => {
    if (document.getElementById(id)) return resolve();
    const s = document.createElement('script');
    s.id = id;
    s.src = src;
    s.async = false;
    s.onload = () => resolve();
    s.onerror = () => reject(new Error('Failed to load: ' + src));
    document.body.appendChild(s);
  });
}

async function ensureJquery() {
  if (window.jQuery && window.$) return window.jQuery;
  await loadScriptOnce('orders-jq', CDN.jquery);
  window.$ = window.jQuery;
  return window.jQuery;
}

async function ensureSummernote() {
  loadCssOnce('orders-summernote-css', CDN.summernoteCss);
  const $ = await ensureJquery();

  // if already loaded on this jQuery, done
  if ($.fn && typeof $.fn.summernote === 'function') return $;

  // load summernote (attaches to window.jQuery)
  await loadScriptOnce('orders-summernote-js', CDN.summernoteJs);

  // re-check
  if (!$.fn || typeof $.fn.summernote !== 'function') {
    console.warn('Summernote failed to attach to jQuery. Check duplicate jQuery or blocked scripts.');
    return $;
  }

  return $;
}

// ---- public API
export async function initModalEditors(scopeEl) {
  const scope = scopeEl || document;
  const $ = await ensureSummernote();

  if (!$.fn || typeof $.fn.summernote !== 'function') {
    console.warn('Summernote is not loaded on window.jQuery');
    return;
  }

  // Initialize all textareas that request summernote
  $(scope).find('textarea[data-summernote="1"]').each(function () {
    const $ta = $(this);

    // prevent double init
    if ($ta.next('.note-editor').length) return;

    const height = parseInt($ta.attr('data-summernote-height') || '320', 10);

    $ta.summernote({
      height,
      dialogsInBody: true,
      disableDragAndDrop: true,
      toolbar: [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'clear']],
        ['fontsize', ['fontsize']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['insert', ['link', 'picture']],
        ['view', ['codeview']]
      ],
      callbacks: {
        onInit: function () {
          // Optional: make editor fit modal nicely
          const $editor = $ta.next('.note-editor');
          $editor.css('width', '100%');
        }
      }
    });
  });
}
