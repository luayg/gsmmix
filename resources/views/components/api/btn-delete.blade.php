@props(['provider'])
<form method="POST" action="{{ route('admin.apis.destroy', $provider) }}" class="d-inline">
  @csrf @method('DELETE')
  <button class="btn btn-danger btn-sm" onclick="return confirm('Delete?')">Delete</button>
</form>
