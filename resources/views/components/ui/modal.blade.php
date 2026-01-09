@props(['id' => 'adminModal', 'size' => 'lg', 'title' => ''])
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-{{ $size }} modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ $title }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        {{ $slot }}
      </div>
    </div>
  </div>
</div>
