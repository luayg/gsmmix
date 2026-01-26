<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ApiProvidersController;

/*
|--------------------------------------------------------------------------
| Admin API Management Routes (ONLY)
|--------------------------------------------------------------------------
| IMPORTANT:
| - Keep ALL /admin/apis routes here
| - Remove the duplicate block from routes/web.php (explained below)
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

        // ✅ هذا هو المسار الذي تستهلكه الواجهة لاختيار مزوّدي الـ API
        Route::get('/options', [ApiProvidersController::class, 'options'])->name('options');

        // ✅ زر Sync now (FIX: كان syncNow - الآن sync)
        Route::post('/{provider}/sync', [ApiProvidersController::class, 'sync'])->name('sync');

        // ✅ خدمات Remote + عمليات الاستيراد
        Route::prefix('{provider}/services')->name('services.')->group(function () {

            Route::get('/imei',   [ApiProvidersController::class,'servicesImei'])->name('imei');
            Route::get('/server', [ApiProvidersController::class,'servicesServer'])->name('server');
            Route::get('/file',   [ApiProvidersController::class,'servicesFile'])->name('file');

            // Bulk import
            Route::post('/import', [ApiProvidersController::class, 'importServices'])->name('import');

            // Wizard import
            Route::post('/import-wizard', [ApiProvidersController::class, 'importServicesWizard'])->name('import_wizard');
        });

    });

});
