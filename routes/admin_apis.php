<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ApiProvidersController;

Route::prefix('admin')->name('admin.')->middleware(['web', 'auth'])->group(function () {
    Route::get('apis', [ApiProvidersController::class, 'index'])->name('apis.index');
    Route::get('apis/create', [ApiProvidersController::class, 'create'])->name('apis.create');
    Route::post('apis', [ApiProvidersController::class, 'store'])->name('apis.store');

    Route::get('apis/{provider}', [ApiProvidersController::class, 'view'])->name('apis.view');
    Route::get('apis/{provider}/edit', [ApiProvidersController::class, 'edit'])->name('apis.edit');
    Route::put('apis/{provider}', [ApiProvidersController::class, 'update'])->name('apis.update');
    Route::delete('apis/{provider}', [ApiProvidersController::class, 'destroy'])->name('apis.destroy');

    Route::post('apis/{provider}/test', [ApiProvidersController::class, 'testConnection'])->name('apis.test');
    Route::post('apis/{provider}/sync', [ApiProvidersController::class, 'syncNow'])->name('apis.sync');

    Route::get('apis/{provider}/services/{kind}', [ApiProvidersController::class, 'services'])
        ->whereIn('kind', ['imei', 'server', 'file'])
        ->name('apis.services');
});
