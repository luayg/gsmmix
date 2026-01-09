<?php

namespace App\Http\Controllers\Admin\Services;

use App\Models\FileService;

class FileServiceController extends BaseServiceController
{
    protected string $model = FileService::class;
    protected string $viewPrefix = 'file';
    protected string $routePrefix = 'admin.services.file';
    protected string $table = 'file_services';
}
