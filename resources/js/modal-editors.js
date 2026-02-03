import $ from 'jquery';
window.$ = window.jQuery = $;

let __snReady = false;

// ✅ تحميل Summernote بعد ضمان window.jQuery (حل تضارب import hoisting)
async function ensureSummernote() {
  if (__snReady) return true;

  await import('summernote/dist/summernote-lite.css');
  await import('summernote/dist/summernote-lite.js');

  __snReady = true;
  return true;
}

export async function initModalEditors(container) {
  const root = container instanceof Element ? container : document;
  const areas = root.querySelectorAll('textarea[data-summernote="1"]');
  if (!areas.length) return;

  await ensureSummernote();

  const jq = window.jQuery || $;

  if (!jq.fn || typeof jq.fn.summernote !== 'function') {
    console.error('Summernote NOT attached to current jQuery instance:', jq?.fn?.jquery);
    return;
  }

  jq(areas).each(function () {
    const $t = jq(this);

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
      callbacks: {
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
