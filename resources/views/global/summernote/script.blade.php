{{-- resources/views/global/summernote/script.blade.php --}}
@once
@push('scripts')
<script>
(function () {
  const nextFrame = () => new Promise(r => requestAnimationFrame(() => r()));
  const waitFrames = async (n=2) => { for (let i=0;i<n;i++) await nextFrame(); };

  async function waitForSummernote(maxMs = 8000) {
    const start = Date.now();
    while (Date.now() - start < maxMs) {
      if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.summernote === 'function') return true;
      await new Promise(r => setTimeout(r, 50));
    }
    return false;
  }

  function getHiddenInput(ta) {
    const sel = ta.getAttribute('data-summernote-hidden') || '';
    if (sel) {
      try {
        const el = ta.closest('form')?.querySelector(sel) || document.querySelector(sel);
        if (el) return el;
      } catch (_) {}
    }
    const form = ta.closest('form');
    if (!form) return null;
    return form.querySelector('#infoHidden') || form.querySelector('input[name="info"]') || null;
  }

  function syncOne(ta) {
    const $ = window.jQuery;
    if (!$ || !$.fn || typeof $.fn.summernote !== 'function') return;
    try {
      if (!$(ta).data('summernote')) return;
      const hidden = getHiddenInput(ta);
      if (hidden) hidden.value = $(ta).summernote('code') || '';
    } catch (_) {}
  }

  // ✅ NEW: Set HTML inside Summernote + keep hidden in sync
  function setOneHtml(ta, html) {
    const val = (html === undefined || html === null) ? '' : String(html);

    // always keep textarea value updated (important BEFORE init)
    try { ta.value = val; } catch (_) {}

    // keep hidden input updated
    const hidden = getHiddenInput(ta);
    if (hidden) hidden.value = val;

    // if summernote already initialized -> update editor content
    const $ = window.jQuery;
    if ($ && $.fn && typeof $.fn.summernote === 'function') {
      try {
        if ($(ta).data('summernote')) $(ta).summernote('code', val);
      } catch (_) {}
    }
  }

  // ✅ Exposed helper (you already call it in service-modal)
  window.setSummernoteHtmlIn = function setSummernoteHtmlIn(scopeEl, html) {
    const scope = (scopeEl instanceof Element) ? scopeEl : document;
    const textareas = Array.from(scope.querySelectorAll('textarea.summernote'));
    if (!textareas.length) return;
    textareas.forEach(ta => setOneHtml(ta, html));
  };

  window.initSummernoteIn = async function initSummernoteIn(scopeEl) {
    const scope = (scopeEl instanceof Element) ? scopeEl : document;

    const ok = await waitForSummernote();
    if (!ok) {
      console.error('Summernote not ready on window.jQuery (missing $.fn.summernote).');
      return;
    }

    const $ = window.jQuery;
    const textareas = Array.from(scope.querySelectorAll('textarea.summernote'));
    if (!textareas.length) return;

    await waitFrames(2);

    textareas.forEach((ta) => {
      try {
        if ($(ta).data('summernote')) return;

        // ✅ IMPORTANT FIX:
        // قبل تهيئة Summernote، خذ قيمة الـ hidden (infoHidden) وضعها في textarea
        // حتى لا يقوم onInit بمسحها بمحتوى فارغ.
        const hidden = getHiddenInput(ta);
        const initialHtml =
          (hidden && hidden.value !== undefined && hidden.value !== null)
            ? String(hidden.value)
            : (ta.value || '');

        if (initialHtml && initialHtml.trim() !== '') {
          ta.value = initialHtml;
        }

        const height = Number(ta.getAttribute('data-summernote-height') || 320);
        const uploadUrl = ta.getAttribute('data-upload-url') || '';

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
              // ✅ Ensure editor shows hidden value (Summernote may wipe textarea during init)
              const h = getHiddenInput(ta);
              const html =
                (h && h.value !== undefined && h.value !== null)
                  ? String(h.value)
                  : (ta.value || '');

              if (html && html.trim() !== '') {
                try { $(ta).summernote('code', html); } catch (_) {}
              }

              // now safe to sync (will NOT overwrite hidden with empty)
              syncOne(ta);
            },
            onChange: function () { syncOne(ta); },
            onImageUpload: function (files) {
              if (!uploadUrl || !files || !files.length) return;

              const file = files[0];
              const fd = new FormData();
              fd.append('file', file);

              const token =
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                ta.closest('form')?.querySelector('input[name="_token"]')?.value ||
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

        // final sync (safe)
        syncOne(ta);
      } catch (e) {
        console.error('Summernote init failed for textarea', ta, e);
      }
    });
  };

  window.destroySummernoteIn = function destroySummernoteIn(scopeEl) {
    const scope = (scopeEl instanceof Element) ? scopeEl : document;
    const $ = window.jQuery;
    if (!$ || !$.fn || typeof $.fn.summernote !== 'function') return;

    Array.from(scope.querySelectorAll('textarea.summernote')).forEach((ta) => {
      try {
        const $ta = $(ta);
        if ($ta.data('summernote')) $ta.summernote('destroy');
      } catch (_) {}
      try {
        delete ta.dataset.summernoteReady;
        ta.removeAttribute('data-summernote-ready');
      } catch (_) {}
    });
  };

  window.syncSummernoteToHidden = function syncSummernoteToHidden(formEl) {
    const form = (formEl instanceof HTMLFormElement) ? formEl : null;
    if (!form) return;
    Array.from(form.querySelectorAll('textarea.summernote')).forEach((ta) => syncOne(ta));
  };
})();
</script>
@endpush
@endonce