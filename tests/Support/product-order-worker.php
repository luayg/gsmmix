<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Tests\Support\ProductOrderMysqlDatabase::configure();
\Illuminate\Support\Facades\Http::preventStrayRequests();

echo "READY\n";
flush();
try {
    $data = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
    $order = app(\App\Services\Orders\ProductOrderService::class)->create($data, (int) $argv[2]);
    echo json_encode(['ok' => true, 'id' => $order->id], JSON_THROW_ON_ERROR) . "\n";
} catch (\Illuminate\Validation\ValidationException $exception) {
    echo json_encode(['ok' => false, 'errors' => array_keys($exception->errors())], JSON_THROW_ON_ERROR) . "\n";
}
