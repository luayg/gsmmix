<?php

namespace App\Http\Controllers\Admin\Services;

use App\Models\ImeiService;

class ImeiServiceController extends BaseServiceController
{
    protected string $model = ImeiService::class;
    protected string $viewPrefix = 'imei';
    protected string $routePrefix = 'admin.services.imei';
    protected string $table = 'imei_services';
}