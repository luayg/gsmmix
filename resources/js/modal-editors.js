export async function initModalEditors(scopeEl) {
  const root = scopeEl instanceof Element ? scopeEl : document;

  if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.summernote) {
    console.warn('Summernote is not loaded on window.jQuery');
    return;
  }

  const $ = window.jQuery;

  $(root).find('textarea[data-summernote="1"]').each(function () {
    const $t = $(this);

    // لا تعيد تهيئة إذا كان متفعّل
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
