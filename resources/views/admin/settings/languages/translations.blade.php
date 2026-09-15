@extends('layouts.admin')
@section('title', 'Translations - '.$language->name)
@section('content')
<div class="card"><div class="card-header bg-primary text-white d-flex justify-content-between"><span><i class="fas fa-language me-1"></i> {{ $language->name }} translations</span><a href="{{ route('admin.settings.languages') }}" class="btn btn-light btn-sm">Back</a></div><div class="card-body">
  @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <p class="text-muted">Use stable keys such as <code>navigation.home</code>. An empty value removes that translation.</p>
  <form method="POST" action="{{ route('admin.settings.languages.translations.update', $language) }}">@csrf @method('PUT')
    <div id="translationRows">@foreach($translations as $i => $translation)<div class="row g-2 mb-2"><div class="col-md-5"><input readonly class="form-control" name="translations[{{ $i }}][key]" value="{{ $translation->translation_key }}"></div><div class="col-md-7"><textarea class="form-control" rows="1" name="translations[{{ $i }}][value]">{{ $translation->value }}</textarea></div></div>@endforeach</div>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="addTranslation">Add key</button><button class="btn btn-primary btn-sm float-end">Save translations</button>
  </form>
</div></div>
@push('scripts')<script>document.getElementById('addTranslation')?.addEventListener('click',()=>{const box=document.getElementById('translationRows');const i=box.children.length;const row=document.createElement('div');row.className='row g-2 mb-2';row.innerHTML=`<div class="col-md-5"><input required class="form-control" name="translations[${i}][key]" placeholder="page.key"></div><div class="col-md-7"><textarea class="form-control" rows="1" name="translations[${i}][value]" placeholder="Translation"></textarea></div>`;box.appendChild(row);});</script>@endpush
@endsection
