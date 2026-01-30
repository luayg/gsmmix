<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\FileOrder;
use App\Models\FileService;
use App\Models\ApiProvider;
use Illuminate\Database\Eloquent\Model;

class FileOrdersController extends BaseOrdersController
{
    protected string $orderModel  = FileOrder::class;
    protected string $serviceModel = FileService::class;

    protected string $kind = 'file';
    protected string $viewPrefix = 'admin.orders.file';
    protected string $routePrefix = 'admin.orders.file';

    protected function placeDhru(Model $order, ApiProvider $provider, Model $service, array $xmlParams): array
    {
        // هنا لازم يكون عندك storage_path + اسم الملف
        $path = (string)($order->storage_path ?? '');
        if ($path === '' || !is_file(public_path($path)) && !is_file(storage_path('app/'.$path))) {
            return ['ERROR'=>[['MESSAGE'=>'MissingFile','FULL_DESCRIPTION'=>'File not found for this order']]];
        }

        $full = is_file(public_path($path)) ? public_path($path) : storage_path('app/'.$path);
        $fileName = basename($full);
        $fileBase64 = base64_encode(file_get_contents($full));

        return $this->dhru->placeFileOrder($provider, $service->remote_id, $fileName, $fileBase64);
    }

    protected function fetchDetailsDhru(Model $order): array
    {
        return $this->dhru->getFileOrder($order->provider, (string)$order->remote_id);
    }
}
