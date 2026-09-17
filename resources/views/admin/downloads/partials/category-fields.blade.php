<div class="modal-body"><div class="row g-3">
<div class="col-12"><label class="form-label">Category name</label><input class="form-control" name="name" required maxlength="120"></div>
<div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" maxlength="1000"></textarea></div>
<div class="col-md-8"><label class="form-label">Required permission <span class="text-muted">(optional)</span></label><input class="form-control" name="required_permission"></div>
<div class="col-md-4"><label class="form-label">Order</label><input class="form-control" type="number" name="ordering" value="0" min="0"></div>
<div class="col-12"><input type="hidden" name="active" value="0"><div class="form-check form-switch"><input class="form-check-input" id="{{ $prefix }}CategoryActive" type="checkbox" name="active" value="1" checked><label class="form-check-label" for="{{ $prefix }}CategoryActive">Active</label></div></div>
</div></div>
