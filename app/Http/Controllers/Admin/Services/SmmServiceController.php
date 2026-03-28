<?php

namespace App\Http\Controllers\Admin\Services;

use App\Models\SmmService;

class SmmServiceController extends BaseServiceController
{
    protected string $model = SmmService::class;
    protected string $viewPrefix = 'smm';
    protected string $routePrefix = 'admin.services.smm';
    protected string $table = 'smm_services';
}