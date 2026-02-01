<?php

use Illuminate\Support\Facades\Route;

// Admin Controllers
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\UserFinanceController;
use App\Http\Controllers\Admin\ApiProvidersController;
use App\Http\Controllers\Admin\UploadController;

// ✅ Service Management
use App\Http\Controllers\Admin\Services\ServiceGroupController;
use App\Http\Controllers\Admin\Services\ImeiServiceController;
use App\Http\Controllers\Admin\Services\ServerServiceController;
use App\Http\Controllers\Admin\Services\FileServiceController;
use App\Http\Controllers\Admin\Services\CloneController;


use App\Http\Controllers\Admin\Orders\ImeiOrdersController;
use App\Http\Controllers\Admin\Orders\ServerOrdersController;
use App\Http\Controllers\Admin\Orders\FileOrdersController;
use App\Http\Controllers\Admin\Orders\ProductOrdersController;


/*
|--------------------------------------------------------------------------
| Web
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route('admin.dashboard'));
Route::view('/login', 'auth.login')->name('login');

Route::prefix('admin')->name('admin.')->group(function () {

    // Dashboard
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', fn () => view('admin.dashboard'))->name('dashboard');

    // Summernote image upload
    Route::post('/uploads/summernote-image', [UploadController::class, 'summernote'])
        ->name('uploads.summernote');

    /* ====================== Users ====================== */
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/',               [UserController::class, 'index'])->name('index');
        Route::get('/data',           [UserController::class, 'data'])->name('data');

        // filters
        Route::get('/filters/roles',  [UserController::class, 'roles'])->name('roles');
        Route::get('/filters/groups', [UserController::class, 'groups'])->name('groups');

        // CRUD JSON
        Route::post('/',         [UserController::class, 'store'])->name('store');
        Route::get('/{user}',    [UserController::class, 'show'])->name('show');
        Route::put('/{user}',    [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');

        // Modals
        Route::get('/modal/create',          [UserController::class, 'modalCreate'])->name('modal.create');
        Route::get('/{user}/modal/view',     [UserController::class, 'modalView'])->name('modal.view');
        Route::get('/{user}/modal/edit',     [UserController::class, 'modalEdit'])->name('modal.edit');
        Route::get('/{user}/modal/delete',   [UserController::class, 'modalDelete'])->name('modal.delete');
        Route::get('/{user}/modal/services', [UserController::class, 'modalServices'])->name('modal.services');

        // Roles attach
        Route::get ('/{user}/roles', [UserRoleController::class, 'edit'])->name('roles.edit');
        Route::post('/{user}/roles', [UserRoleController::class, 'sync'])->name('roles.sync');

        /* ========= User Finances ========= */
        Route::prefix('{user}/finances')->name('finances.')->group(function () {
            Route::get('/modal',     [UserFinanceController::class, 'modal'])->name('modal');
            Route::get('/summary',   [UserFinanceController::class, 'summary'])->name('summary');
            Route::get('/statement', [UserFinanceController::class, 'statement'])->name('statement');

            // forms
            Route::get('/form/overdraft',   [UserFinanceController::class, 'formOverdraft'])->name('form.overdraft');
            Route::get('/form/add-remove',  [UserFinanceController::class, 'formAddRemove'])->name('form.add_remove');
            Route::get('/form/add-payment', [UserFinanceController::class, 'formAddPayment'])->name('form.add_payment');
            Route::get('/form/gateways',    [UserFinanceController::class, 'formGateways'])->name('form.gateways');

            // actions
            Route::post('/set-overdraft', [UserFinanceController::class, 'setOverdraft'])->name('set_overdraft');
            Route::post('/add-remove',    [UserFinanceController::class, 'addRemoveCredits'])->name('add_remove');
            Route::post('/add-payment',   [UserFinanceController::class, 'addPayment'])->name('add_payment');
        });
        /* ======== End finances ======== */
    });

    /* ====================== Groups ====================== */
    Route::prefix('groups')->name('groups.')->group(function () {
        Route::get('/',             [GroupController::class,'index'])->name('index');
        Route::get('/data',         [GroupController::class,'data'])->name('data');

        // ✅ هذا هو المسار المطلوب في Blade: route('admin.groups.options')
        Route::get('/options',      [GroupController::class,'options'])->name('options');

        Route::post('/',            [GroupController::class,'store'])->name('store');
        Route::put('/{group}',      [GroupController::class,'update'])->name('update');
        Route::delete('/{group}',   [GroupController::class,'destroy'])->name('destroy');
        Route::get('/{group}',      [GroupController::class,'show'])->name('show');

        // Ajax modals
        Route::get('/modal/create',         [GroupController::class, 'modalCreate'])->name('modal.create');
        Route::get('/{group}/modal/view',   [GroupController::class, 'modalView'])->name('modal.view');
        Route::get('/{group}/modal/edit',   [GroupController::class, 'modalEdit'])->name('modal.edit');
        Route::get('/{group}/modal/delete', [GroupController::class, 'modalDelete'])->name('modal.delete');
    });

    /* ====================== Roles ====================== */
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/',              [RoleController::class, 'index'])->name('index');
        Route::get('/data',          [RoleController::class, 'data'])->name('data');
        Route::post('/',             [RoleController::class, 'store'])->name('store');
        Route::put('/{role}',        [RoleController::class, 'update'])->name('update');
        Route::delete('/{role}',     [RoleController::class, 'destroy'])->name('destroy');
        Route::get('/{role}/perms',  [RoleController::class, 'editPerms'])->name('perms');
        Route::post('/{role}/perms', [RoleController::class, 'syncPerms'])->name('perms.sync');
    });

    /* ====================== Permissions ====================== */
    Route::prefix('permissions')->name('permissions.')->group(function () {
        Route::get('/',        [PermissionController::class,'index'])->name('index');
        Route::get('/data',    [PermissionController::class,'data'])->name('data');
        Route::post('/',       [PermissionController::class,'store'])->name('store');
        Route::put('/{perm}',  [PermissionController::class,'update'])->name('update');
        Route::delete('/{perm}', [PermissionController::class,'destroy'])->name('destroy');
    });

    /* ================== ✅ Service Management ==================== */
    Route::prefix('service-management')->name('services.')->group(function () {
        // Groups CRUD
        Route::resource('groups', ServiceGroupController::class)->except(['show'])->names('groups');

        // Ajax: groups filtered by type (imei/server/file)
        Route::get('groups/options', [ServiceGroupController::class, 'options'])->name('groups.options');

        // ===== IMEI services =====
        Route::resource('imei-services', ImeiServiceController::class)->except(['show'])->names('imei');

        // JSON + extra actions needed by Vue/modals
        Route::get('imei-services/{service}/json',    [ImeiServiceController::class, 'showJson'])->name('imei.show.json');
        Route::post('imei-services/{service}/toggle', [ImeiServiceController::class, 'toggle'])->name('imei.toggle');

        // Modals
        Route::get('imei-services/modal/create',         [ImeiServiceController::class, 'modalCreate'])->name('imei.modal.create');
        Route::get('imei-services/{service}/modal/edit', [ImeiServiceController::class, 'modalEdit'])->name('imei.modal.edit');

        // ===== Server services =====
        Route::resource('server-services', ServerServiceController::class)->except(['show'])->names('server');
        Route::get('server-services/{service}/json',    [ServerServiceController::class, 'showJson'])->name('server.show.json');
        Route::post('server-services/{service}/toggle', [ServerServiceController::class, 'toggle'])->name('server.toggle');

        // ===== File services =====
        Route::resource('file-services', FileServiceController::class)->except(['show'])->names('file');
        Route::get('file-services/{service}/json',    [FileServiceController::class, 'showJson'])->name('file.show.json');
        Route::post('file-services/{service}/toggle', [FileServiceController::class, 'toggle'])->name('file.toggle');

        // ===== Clone from API =====
        Route::get('clone/modal',             [CloneController::class, 'modal'])->name('clone.modal');
        Route::get('clone/provider-services', [CloneController::class, 'providerServices'])->name('clone.provider_services');
    });

    /* ======== روابط مختصرة ======== */
    Route::get('/services/imei',   fn () => redirect()->route('admin.services.imei.index'));
    Route::get('/services/server', fn () => redirect()->route('admin.services.server.index'));
    Route::get('/services/file',   fn () => redirect()->route('admin.services.file.index'));
    Route::get('/services/groups', fn () => redirect()->route('admin.services.groups.index'));

 /* ===== IMEI Orders ===== */
    Route::prefix('imei')->name('imei.')->group(function () {
        Route::get('/', [ImeiOrdersController::class, 'index'])->name('index');

        // Modals
        Route::get('/modal/create', [ImeiOrdersController::class, 'modalCreate'])->name('modal.create');
        Route::get('/{order}/modal/view', [ImeiOrdersController::class, 'modalView'])->name('modal.view');
        Route::get('/{order}/modal/edit', [ImeiOrdersController::class, 'modalEdit'])->name('modal.edit');

        // Actions
        Route::post('/', [ImeiOrdersController::class, 'store'])->name('store');
        Route::put('/{order}', [ImeiOrdersController::class, 'update'])->name('update');
    });

    /* ===== Server Orders ===== */
    Route::prefix('server')->name('server.')->group(function () {
        Route::get('/', [ServerOrdersController::class, 'index'])->name('index');

        Route::get('/modal/create', [ServerOrdersController::class, 'modalCreate'])->name('modal.create');
        Route::get('/{order}/modal/view', [ServerOrdersController::class, 'modalView'])->name('modal.view');
        Route::get('/{order}/modal/edit', [ServerOrdersController::class, 'modalEdit'])->name('modal.edit');

        Route::post('/', [ServerOrdersController::class, 'store'])->name('store');
        Route::put('/{order}', [ServerOrdersController::class, 'update'])->name('update');
    });

    /* ===== File Orders ===== */
    Route::prefix('file')->name('file.')->group(function () {
        Route::get('/', [FileOrdersController::class, 'index'])->name('index');

        Route::get('/modal/create', [FileOrdersController::class, 'modalCreate'])->name('modal.create');
        Route::get('/{order}/modal/view', [FileOrdersController::class, 'modalView'])->name('modal.view');
        Route::get('/{order}/modal/edit', [FileOrdersController::class, 'modalEdit'])->name('modal.edit');

        Route::post('/', [FileOrdersController::class, 'store'])->name('store');
        Route::put('/{order}', [FileOrdersController::class, 'update'])->name('update');
    });

    /* ===== Product Orders ===== */
    Route::prefix('product')->name('product.')->group(function () {
        Route::get('/', [ProductOrdersController::class, 'index'])->name('index');

        // حالياً ستاندرد بدون API (جاهز لتطوير لاحقاً)
        Route::get('/modal/create', [ProductOrdersController::class, 'modalCreate'])->name('modal.create');
        Route::get('/{order}/modal/view', [ProductOrdersController::class, 'modalView'])->name('modal.view');
        Route::get('/{order}/modal/edit', [ProductOrdersController::class, 'modalEdit'])->name('modal.edit');

        Route::post('/', [ProductOrdersController::class, 'store'])->name('store');
        Route::put('/{order}', [ProductOrdersController::class, 'update'])->name('update');
    });
});
/* ================== End Orders ==================== */






    /* ================== Finances (placeholders) ==================== */
    Route::prefix('finances')->name('finances.')->group(function () {
        Route::get('/', fn () => 'Finances home')->name('index');
        Route::get('/invoices',     fn () => 'Invoices')->name('invoices.index');
        Route::get('/statements',   fn () => 'Statements')->name('statements.index');
        Route::get('/transactions', fn () => 'Transactions')->name('transactions.index');
    });

    /* ================== Store (placeholders) ==================== */
    Route::prefix('store')->name('store.')->group(function () {
        Route::get('/categories', fn () => 'Product categories')->name('categories.index');
        Route::get('/products',   fn () => 'Products')->name('products.index');
    });
/* ================== API Management (Providers) ====================
| Moved to routes/admin_apis.php
|================================================================== */


    /* ================== CMS-like sections (placeholders) ==================== */
    Route::prefix('pages')->name('pages.')->group(function () {
        Route::get('/', fn () => 'Pages')->name('index');
    });

    Route::prefix('sources')->name('sources.')->group(function () {
        Route::get('/', fn () => 'Sources')->name('index');
    });

    Route::prefix('replies')->name('replies.')->group(function () {
    Route::get('/', fn () => 'Replies')->name('index');
});


    /* ================== Downloads (placeholders) ==================== */
    Route::prefix('downloads')->name('downloads.')->group(function () {
        Route::get('/', fn () => 'Downloads')->name('index');
        Route::get('/categories', fn () => 'Download categories')->name('categories.index');
    });

    /* ================== Settings/System/Reports/Logs ==================== */
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/general',    fn () => 'General settings')->name('general');
        Route::get('/mail',       fn () => 'Mail settings')->name('mail');
        Route::get('/payment',    fn () => 'Payment settings')->name('payment');
        Route::get('/languages',  fn () => 'Languages')->name('languages');
        Route::get('/currencies', fn () => 'Currencies')->name('currencies');
    });

    Route::prefix('system')->name('system.')->group(function () {
        Route::get('/filemanager', fn () => 'File manager')->name('filemanager');
        Route::get('/update',      fn () => 'Update')->name('update');
        Route::get('/maintenance', fn () => 'Maintenance')->name('maintenance');
        Route::get('/backups',     fn () => 'Backups')->name('backups');
    });

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/users',    fn () => 'User reports')->name('users');
        Route::get('/services', fn () => 'Service reports')->name('services');
        Route::get('/products', fn () => 'Product reports')->name('products');
    });

    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('/access',   fn () => 'Access logs')->name('access');
        Route::get('/activity', fn () => 'Activity logs')->name('activity');
        Route::get('/error',    fn () => 'Error logs')->name('error');
    });

});
require __DIR__ . '/admin_apis.php';
require __DIR__ . '/auth.php';
