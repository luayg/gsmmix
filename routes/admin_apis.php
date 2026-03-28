<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ApiProvidersController;
use App\Http\Controllers\Admin\Api\RemoteServerServicesController;

/*
|--------------------------------------------------------------------------
| Admin API Management Routes (ONLY)
|--------------------------------------------------------------------------
| IMPORTANT:
| - Keep ALL /admin/apis routes here
| - Do NOT duplicate them in routes/web.php
*/

Route::prefix('admin')->name('admin.')->group(function () {

    // اختياري: رابط مختصر
    Route::get('/api', fn () => redirect()->route('admin.apis.index'));

    Route::prefix('apis')->name('apis.')->group(function () {

        Route::get('/',                [ApiProvidersController::class,'index'])->name('index');
        Route::get('/create',          [ApiProvidersController::class,'create'])->name('create');
        Route::post('/',               [ApiProvidersController::class,'store'])->name('store');

        Route::get('/{provider}/view', [ApiProvidersController::class,'view'])->name('view');
        Route::get('/{provider}/edit', [ApiProvidersController::class,'edit'])->name('edit');
        Route::put('/{provider}',      [ApiProvidersController::class,'update'])->name('update');
        Route::delete('/{provider}',   [ApiProvidersController::class,'destroy'])->name('destroy');

        // options
        Route::get('/options', [ApiProvidersController::class, 'options'])->name('options');

        // Sync now
        Route::post('/{provider}/sync', [ApiProvidersController::class, 'sync'])->name('sync');

        // Server Remote Services (page-style)
        Route::prefix('{provider}/remote/server')->name('remote.server.')->group(function () {
            Route::get('/',        [RemoteServerServicesController::class, 'index'])->name('index');
            Route::get('/import',  [RemoteServerServicesController::class, 'importPage'])->name('import_page');
            Route::post('/import', [RemoteServerServicesController::class, 'import'])->name('import');
            Route::get('/services-json', [RemoteServerServicesController::class, 'servicesJson'])->name('services_json');
        });

        // Remote services modals/import
        Route::prefix('{provider}/services')->name('services.')->group(function () {

            Route::get('/imei',   [ApiProvidersController::class,'servicesImei'])->name('imei');

            // server still redirects to dedicated page
            Route::get('/server', fn ($provider) => redirect()->route('admin.apis.remote.server.index', $provider))
                ->name('server');

            Route::get('/file',   [ApiProvidersController::class,'servicesFile'])->name('file');
            Route::get('/smm',    [ApiProvidersController::class,'servicesSmm'])->name('smm');

            // Bulk import
            Route::post('/import', [ApiProvidersController::class, 'importServices'])->name('import');

            // Wizard import
            Route::post('/import-wizard', [ApiProvidersController::class, 'importServicesWizard'])->name('import_wizard');
        });

    });

});