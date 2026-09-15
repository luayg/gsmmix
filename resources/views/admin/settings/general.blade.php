@extends('layouts.admin')

@section('title', 'General settings')

@section('content')
<div class="card">
  <div class="card-header bg-primary text-white"><i class="fas fa-sliders-h me-1"></i> General settings</div>
  <div class="card-body">
    @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('admin.settings.general.update') }}" enctype="multipart/form-data">
      @csrf @method('PUT')
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Site name</label><input name="site_name" class="form-control" maxlength="120" required value="{{ old('site_name', $settings['general.site_name']) }}"></div>
        <div class="col-md-6"><label class="form-label">Tagline</label><input name="site_tagline" class="form-control" maxlength="255" value="{{ old('site_tagline', $settings['general.site_tagline']) }}"></div>
        <div class="col-md-6"><label class="form-label">Contact email</label><input type="email" name="contact_email" class="form-control" value="{{ old('contact_email', $settings['general.contact_email']) }}"></div>
        <div class="col-md-6"><label class="form-label">Contact phone</label><input name="contact_phone" class="form-control" maxlength="50" value="{{ old('contact_phone', $settings['general.contact_phone']) }}"></div>
        <div class="col-md-6"><label class="form-label">Timezone</label><select name="timezone" class="form-select" required>@foreach(timezone_identifiers_list() as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', $settings['general.timezone']) === $timezone)>{{ $timezone }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Date format</label><select name="date_format" class="form-select">@foreach(['Y-m-d','d/m/Y','m/d/Y','d M Y'] as $format)<option value="{{ $format }}" @selected(old('date_format', $settings['general.date_format']) === $format)>{{ now()->format($format) }} ({{ $format }})</option>@endforeach</select></div>
        <div class="col-md-6">
          <label class="form-label">Site logo</label><input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp">
          @if($settings['general.logo'])<div class="mt-2"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings['general.logo']) }}" alt="Current logo" style="max-height:72px;max-width:240px"></div>@endif
        </div>
        <div class="col-12"><hr><h6>Enabled modules</h6></div>
        @foreach(['registration_enabled'=>'Public registration','service_imei_enabled'=>'IMEI services','service_server_enabled'=>'Server services','service_file_enabled'=>'File services','service_smm_enabled'=>'SMM services','store_enabled'=>'Retail store'] as $key => $label)
          <div class="col-md-4"><div class="form-check form-switch"><input type="hidden" name="{{ $key }}" value="0"><input class="form-check-input" type="checkbox" name="{{ $key }}" value="1" id="{{ $key }}" @checked(old($key, $settings['general.'.$key]))><label class="form-check-label" for="{{ $key }}">{{ $label }}</label></div></div>
        @endforeach
      </div>
      <div class="mt-4 text-end"><button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i> Save settings</button></div>
    </form>
  </div>
</div>
@endsection
