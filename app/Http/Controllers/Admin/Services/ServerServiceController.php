<?php

namespace App\Http\Controllers\Admin\Services;

use App\Models\ServerService;

class ServerServiceController extends BaseServiceController
{
    protected string $model = ServerService::class;
    protected string $viewPrefix = 'server';
    protected string $routePrefix = 'admin.services.server';
    protected string $table = 'server_services';
}
