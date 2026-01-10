{{-- resources/views/admin/services/partials/_additional_tab.blade.php --}}

@php
  // Groups list passed from controller or fallback
  $groups = $groups ?? \App\Models\UserGroup::query()->orderBy('id')->get();
@endphp

<div class="row g-3">

  {{-- LEFT: Custom fields --}}
  <div class="col-md-6">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="mb-0">Custom fields</h6>
      <a href="#" class="text-decoration-none fw-bold" id="btnAddField">Add field</a>
    </div>

    <div class="border rounded p-3 bg-white">

      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="fieldActive" checked>
        <label class="form-check-label" for="fieldActive">Active</label>
      </div>

      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label mb-1">Name</label>
          <input class="form-control" name="custom_fields[name][]" placeholder="Name">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Field type</label>
          <select class="form-select" name="custom_fields[type][]">
            <option value="text">Text</option>
            <option value="number">Number</option>
            <option value="select">Select</option>
            <option value="textarea">Textarea</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Input name</label>
          <input class="form-control" name="custom_fields[input_name][]" placeholder="Machine name of your input">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Description</label>
          <input class="form-control" name="custom_fields[description][]" placeholder="Description">
        </div>

        <div class="col-md-3">
          <label class="form-label mb-1">Minimum</label>
          <input class="form-control" name="custom_fields[min][]" placeholder="0">
        </div>

        <div class="col-md-3">
          <label class="form-label mb-1">Maximum</label>
          <input class="form-control" name="custom_fields[max][]" placeholder="Unlimited">
        </div>

        <div class="col-md-3">
          <label class="form-label mb-1">Validation</label>
          <select class="form-select" name="custom_fields[validation][]">
            <option value="">None</option>
            <option value="email">Email</option>
            <option value="imei">IMEI</option>
            <option value="serial">Serial</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label mb-1">Required</label>
          <select class="form-select" name="custom_fields[required][]">
            <option value="0">No</option>
            <option value="1">Yes</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  {{-- RIGHT: Groups / Pricing table --}}
  <div class="col-md-6">
    <h6 class="mb-2">Groups</h6>

    <div class="border rounded bg-white">

      @foreach($groups as $g)
        <div class="border-bottom p-2 fw-bold bg-light">
          {{ $g->name }}
        </div>

        <div class="p-2">

          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label mb-1">Price</label>
              <div class="input-group">
                <input type="number" step="0.01"
                       name="group_price[{{ $g->id }}]"
                       class="form-control group-price"
                       value="0">
                <span class="input-group-text">Credits</span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Discount</label>
              <div class="input-group">
                <input type="number" step="0.01"
                       name="group_discount[{{ $g->id }}]"
                       class="form-control group-discount"
                       value="0">
                <button class="btn btn-light btnResetRow" type="button">Reset</button>
              </div>
            </div>
          </div>

        </div>
      @endforeach

    </div>

  </div>
</div>
