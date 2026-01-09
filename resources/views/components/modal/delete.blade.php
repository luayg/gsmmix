@props([
  'id' => 'modalDelete',
  'title' => 'Delete',
  'confirm' => 'Delete',
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="{{ $id }}-form" method="POST" action="#">
      @csrf @method('DELETE')

      <div class="modal-header">
        <h5 class="modal-title">{{ $title }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p id="{{ $id }}-msg" class="mb-0">Are you sure?</p>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">{{ $confirm }}</button>
      </div>
    </form>
  </div>
</div>
