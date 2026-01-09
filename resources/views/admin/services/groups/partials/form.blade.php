{{-- admin/services/groups/partials/form.blade.php --}}
<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control" required
           value="{{ old('name', $group->name ?? '') }}">
    @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-3">
    <label class="form-label">Type</label>
    @php $types = ['imei' => 'IMEI', 'server' => 'Server', 'file' => 'File']; @endphp
    <select name="type" class="form-select">
      @foreach($types as $k => $v)
        <option value="{{ $k }}" @selected(old('type', $group->type ?? '') == $k)>{{ $v }}</option>
      @endforeach
    </select>
    @error('type') <div class="text-danger small">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-3">
    <label class="form-label">Ordering</label>
    <input type="number" name="ordering" class="form-control"
           value={{ old('ordering', $group->ordering ?? 1) }}>
    @error('ordering') <div class="text-danger small">{{ $message }}</div> @enderror
  </div>

  <div class="col-12">
    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" id="activeSwitch" name="active" value="1"
             {{ old('active', $group->active ?? 1) ? 'checked' : '' }}>
      <label class="form-check-label" for="activeSwitch">Active</label>
    </div>
  </div>

  <div class="col-12">
    <label class="form-label">Notes</label>
    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $group->notes ?? '') }}</textarea>
  </div>
</div>
