@extends('layouts.app')

@section('title')
    File Services
@endsection

@section('content')
    @php
        // خدمات تم إضافتها مسبقًا لهذا الـ provider
        $existingRemoteIds = \App\Models\FileService::where('supplier_id', $provider->id)
            ->pluck('remote_id')
            ->toArray();
    @endphp

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">File Services - {{ $provider->name }}</h4>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                            <tr>
                                <th>Remote ID</th>
                                <th>Name</th>
                                <th>Credits</th>
                                <th>Time</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($services as $service)
                                @php
                                    $remoteId = data_get($service, 'SERVICEID');
                                    $isAdded = $remoteId !== null && in_array($remoteId, $existingRemoteIds, true);
                                @endphp

                                <tr>
                                    <td>{{ $remoteId }}</td>
                                    <td>{{ data_get($service, 'SERVICENAME') }}</td>
                                    <td>{{ data_get($service, 'CREDIT') }}</td>
                                    <td>{{ data_get($service, 'TIME') }}</td>
                                    <td>
                                        @if ($isAdded)
                                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                                                Added
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                class="btn btn-primary btn-sm"
                                                data-create-service="true"
                                                data-service-type="file"
                                                data-provider-name="{{ $provider->name }}"
                                                data-service='@json($service)'
                                            >
                                                Clone
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>

    @include('admin.api.providers.modals.services')
@endsection
