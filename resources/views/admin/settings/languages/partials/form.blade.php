@php($prefix = $language ? 'edit_'.$language->id.'_' : '')
<div class="row g-3">
  <div class="col-md-6"><label class="form-label">Name</label><input name="name" required maxlength="100" class="form-control" value="{{ $language?->name }}"></div>
  <div class="col-md-6"><label class="form-label">Native name</label><input name="native_name" required maxlength="100" class="form-control" value="{{ $language?->native_name }}"></div>
  <div class="col-md-4"><label class="form-label">Code</label><input name="code" required maxlength="3" class="form-control" placeholder="en" value="{{ $language?->code }}"></div>
  <div class="col-md-4"><label class="form-label">Locale</label><input name="locale" required maxlength="20" class="form-control" placeholder="en or ar_JO" value="{{ $language?->locale }}"></div>
  <div class="col-md-4"><label class="form-label">Direction</label><select name="direction" class="form-select"><option value="ltr" @selected(($language?->direction ?? 'ltr') === 'ltr')>LTR</option><option value="rtl" @selected($language?->direction === 'rtl')>RTL</option></select></div>
  <div class="col-md-6"><label class="form-label">Flag / short icon</label><input name="flag" maxlength="10" class="form-control" value="{{ $language?->flag }}" placeholder="🇬🇧"></div>
  <div class="col-md-6"><label class="form-label">Ordering</label><input type="number" min="0" name="ordering" class="form-control" value="{{ $language?->ordering ?? 0 }}"></div>
  <div class="col-6"><input type="hidden" name="active" value="0"><div class="form-check form-switch"><input class="form-check-input" id="{{ $prefix }}languageActive" type="checkbox" name="active" value="1" @checked($language?->active ?? true)><label class="form-check-label" for="{{ $prefix }}languageActive">Active</label></div></div>
  <div class="col-6"><input type="hidden" name="is_default" value="0"><div class="form-check form-switch"><input class="form-check-input" id="{{ $prefix }}languageDefault" type="checkbox" name="is_default" value="1" @checked($language?->is_default ?? false)><label class="form-check-label" for="{{ $prefix }}languageDefault">Default language</label></div></div>
</div>
