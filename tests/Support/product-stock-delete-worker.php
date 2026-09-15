<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Tests\Support\ProductOrderMysqlDatabase::configure();
\Illuminate\Support\Facades\Http::preventStrayRequests();

[$model, $controller] = match ($argv[1]) {
    'product' => [\App\Models\Product::class, \App\Http\Controllers\Admin\ProductController::class],
    'reply' => [\App\Models\LocalReply::class, \App\Http\Controllers\Admin\LocalReplyController::class],
    default => throw new \InvalidArgumentException('Unknown synthetic stock target.'),
};
$row = $model::findOrFail((int) $argv[2]);
$table = $row->getTable();
$announced = false;
// The parent holds this record. Signal immediately before a query that must wait for it.
\Illuminate\Support\Facades\DB::connection()->beforeExecuting(function ($sql) use ($table, &$announced): void {
    if (!$announced && str_contains($sql, $table)
        && (str_contains(strtolower($sql), 'for update') || str_starts_with(strtolower($sql), 'delete '))) {
        $announced = true;
        echo "BARRIER\n";
        flush();
    }
});
$response = app($controller)->destroy($row);
echo json_encode(['status' => $response->getStatusCode()], JSON_THROW_ON_ERROR) . "\n";
