@props([
  'id' => 'modalEdit',
  'title' => 'Edit',
  'action' => '#',
  'method' => 'POST',   // عند التعديل: PUT
  'submit' => 'Save',
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="{{ $id }}-form" action="{{ $action }}" method="POST">
      @csrf
      @if (strtoupper($method) === 'PUT') @method('PUT') @endif

      <div class="modal-header">
        <h5 class="modal-title">{{ $title }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        {{-- حقول النموذج (ضعها من الصفحة باستخدام slot) --}}
        {{ $slot }}
        <div class="invalid-feedback d-block" id="{{ $id }}-errors" style="display:none"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">{{ $submit }}</button>
      </div>
    </form>
  </div>
</div>
