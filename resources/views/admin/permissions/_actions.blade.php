<div class="btn-group btn-group-sm" role="group">
  <button type="button"
          class="btn btn-warning js-perm-edit"
          data-id="{{ $p->id }}"
          data-name="{{ e($p->name) }}"
          data-guard="{{ $p->guard_name }}">
    Edit
  </button>

  <button type="button"
          class="btn btn-danger js-perm-del"
          data-id="{{ $p->id }}"
          data-name="{{ e($p->name) }}">
    Delete
  </button>
</div>
