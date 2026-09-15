@extends('layouts.admin')
@section('title', 'Languages')
@section('content')
<div class="card">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center"><span><i class="fas fa-language me-1"></i> Languages</span><button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#createLanguage">Add language</button></div>
  <div class="card-body">
    @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="table-responsive"><table class="table table-striped align-middle">
      <thead><tr><th>Language</th><th>Code / locale</th><th>Direction</th><th>Translations</th><th>Status</th><th>Order</th><th class="text-end">Actions</th></tr></thead>
      <tbody>@foreach($languages as $language)<tr>
        <td><span class="me-1">{{ $language->flag }}</span>{{ $language->name }} <small class="text-muted">({{ $language->native_name }})</small>@if($language->is_default)<span class="badge bg-primary ms-1">Default</span>@endif</td>
        <td><code>{{ $language->code }}</code> / <code>{{ $language->locale }}</code></td><td>{{ strtoupper($language->direction) }}</td><td>{{ $language->translations_count }}</td>
        <td><span class="badge {{ $language->active ? 'bg-success' : 'bg-secondary' }}">{{ $language->active ? 'Active' : 'Inactive' }}</span></td><td>{{ $language->ordering }}</td>
        <td class="text-end text-nowrap"><a class="btn btn-info btn-sm" href="{{ route('admin.settings.languages.translations', $language) }}">Translations</a> <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editLanguage{{ $language->id }}">Edit</button> @unless($language->is_default)<form class="d-inline" method="POST" action="{{ route('admin.settings.languages.destroy', $language) }}" onsubmit="return confirm('Delete this language and its translations?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Delete</button></form>@endunless</td>
      </tr>@endforeach</tbody>
    </table></div>
  </div>
</div>

<div class="modal fade" id="createLanguage" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.settings.languages.store') }}">@csrf<div class="modal-header"><h5 class="modal-title">Add language</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">@include('admin.settings.languages.partials.form', ['language' => null])</div><div class="modal-footer"><button class="btn btn-primary">Create</button></div></form></div></div>
@foreach($languages as $language)<div class="modal fade" id="editLanguage{{ $language->id }}" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.settings.languages.update', $language) }}">@csrf @method('PUT')<div class="modal-header"><h5 class="modal-title">Edit {{ $language->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">@include('admin.settings.languages.partials.form', ['language' => $language])</div><div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div>@endforeach
@endsection
