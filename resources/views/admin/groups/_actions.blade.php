<div class="btn-group btn-group-sm" role="group">
  <button type="button"
          class="btn btn-primary btn-view-group"
          data-id="{{ $g->id }}" data-name="{{ $g->name }}">
    <i class="fas fa-eye"></i> View
  </button>
  <button type="button"
          class="btn btn-warning btn-edit-group"
          data-id="{{ $g->id }}" data-name="{{ $g->name }}">
    <i class="fas fa-edit"></i> Edit
  </button>
  <button type="button"
          class="btn btn-danger btn-del-group"
          data-id="{{ $g->id }}" data-name="{{ $g->name }}">
    <i class="fas fa-trash"></i> Delete
  </button>
</div>
