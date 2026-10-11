<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DemoDataController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VariantAttributeController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\CustomerReturnController;
use App\Http\Controllers\DamageCaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeClaimController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockEntryController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => to_route('dashboard'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->middleware('throttle:120,1')->name('search');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{notification}', [NotificationController::class, 'read'])->name('notifications.read');

    Route::middleware(['can:products.view', 'throttle:240,1'])->group(function () {
        Route::get('lookup/products', [LookupController::class, 'products'])->name('lookup.products');
        Route::get('lookup/products/{product}/variants', [LookupController::class, 'variants'])->name('lookup.variants');
        Route::get('lookup/variants', [LookupController::class, 'allVariants'])->name('lookup.all-variants');
    });

    Route::middleware('can:products.view')->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->middleware('can:products.manage')->name('products.create');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    });

    Route::middleware('can:products.manage')->group(function () {
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->name('variants.store');
        Route::put('variants/{variant}', [ProductVariantController::class, 'update'])->name('variants.update');
        Route::post('variants/{variant}/archive', [ProductVariantController::class, 'archive'])->name('variants.archive');
        Route::post('variants/{variant}/restore', [ProductVariantController::class, 'restore'])->name('variants.restore');
    });

    Route::get('stock/{type}', [StockEntryController::class, 'create'])->whereIn('type', ['in', 'out', 'opening'])->name('stock.create');
    Route::post('stock/{type}', [StockEntryController::class, 'store'])->whereIn('type', ['in', 'out', 'opening'])->name('stock.store');

    Route::middleware('can:movements.view')->group(function () {
        Route::get('movements', [StockMovementController::class, 'index'])->name('movements.index');
        Route::get('movements/{movement}', [StockMovementController::class, 'show'])->name('movements.show');
    });

    Route::get('returns', [CustomerReturnController::class, 'index'])->middleware('can:movements.view')->name('returns.index');
    Route::get('returns/create', [CustomerReturnController::class, 'create'])->middleware('can:returns.create')->name('returns.create');
    Route::post('returns', [CustomerReturnController::class, 'store'])->name('returns.store');
    Route::get('returns/{return}', [CustomerReturnController::class, 'show'])->middleware('can:movements.view')->name('returns.show');
    Route::post('returns/{return}/inspect', [CustomerReturnController::class, 'inspect'])->name('returns.inspect');

    Route::get('damage', [DamageCaseController::class, 'index'])->middleware('can:movements.view')->name('damage.index');
    Route::get('damage/create', [DamageCaseController::class, 'create'])->middleware('can:damage.create')->name('damage.create');
    Route::post('damage', [DamageCaseController::class, 'store'])->name('damage.store');
    Route::get('damage/{damageCase}', [DamageCaseController::class, 'show'])->middleware('can:movements.view')->name('damage.show');
    Route::get('damage/images/{image}', [DamageCaseController::class, 'image'])->middleware('can:movements.view')->name('damage.images.show');
    Route::post('damage/{damageCase}/claims', [DamageCaseController::class, 'submitClaim'])->name('damage.claims.store');

    Route::middleware('can:claims.view')->group(function () {
        Route::get('claims', [ExchangeClaimController::class, 'index'])->name('claims.index');
        Route::get('claims/{claim}', [ExchangeClaimController::class, 'show'])->name('claims.show');
    });
    Route::post('claims/{claim}/review', [ExchangeClaimController::class, 'review'])->name('claims.review');
    Route::post('claims/{claim}/agreement', [ExchangeClaimController::class, 'recordAgreement'])->name('claims.agreement');
    Route::post('claims/{claim}/approve', [ExchangeClaimController::class, 'approve'])->name('claims.approve');
    Route::post('claims/{claim}/reject', [ExchangeClaimController::class, 'reject'])->name('claims.reject');
    Route::post('claims/{claim}/dispatch', [ExchangeClaimController::class, 'dispatch'])->name('claims.dispatch');
    Route::post('claims/{claim}/receive', [ExchangeClaimController::class, 'receive'])->name('claims.receive');
    Route::post('claims/{claim}/close-short', [ExchangeClaimController::class, 'closeShort'])->name('claims.close-short');

    Route::get('suppliers', [SupplierController::class, 'index'])->middleware('can:suppliers.view')->name('suppliers.index');
    Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('can:suppliers.view')->name('suppliers.show');
    Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');

    Route::get('adjustments', [StockAdjustmentController::class, 'index'])->middleware('can:movements.view')->name('adjustments.index');
    Route::get('adjustments/create', [StockAdjustmentController::class, 'create'])->middleware('can:adjustments.create')->name('adjustments.create');
    Route::post('adjustments', [StockAdjustmentController::class, 'store'])->name('adjustments.store');
    Route::get('adjustments/{adjustment}', [StockAdjustmentController::class, 'show'])->middleware('can:movements.view')->name('adjustments.show');
    Route::post('adjustments/{adjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('adjustments.approve');
    Route::post('adjustments/{adjustment}/reject', [StockAdjustmentController::class, 'reject'])->name('adjustments.reject');

    Route::middleware('can:reports.view')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    });
    Route::get('reports/{report}/export/{format}', [ReportController::class, 'export'])->middleware(['can:reports.export', 'throttle:20,1'])->name('reports.export');

    Route::get('activity', [ActivityLogController::class, 'index'])->middleware('can:activity.view')->name('activity.index');
    Route::get('reconciliation', ReconciliationController::class)->middleware('can:ledger.reconcile')->name('reconciliation.index');

    Route::middleware('can:stock.import')->group(function () {
        Route::get('import', [ImportController::class, 'create'])->name('import.create');
        Route::post('import', [ImportController::class, 'store'])->middleware('throttle:10,1')->name('import.store');
        Route::get('import/template', [ImportController::class, 'template'])->name('import.template');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:catalog.manage')->group(function () {
            Route::get('attributes', [VariantAttributeController::class, 'index'])->name('attributes.index');
            Route::post('attributes', [VariantAttributeController::class, 'store'])->name('attributes.store');
            Route::put('attributes/{attribute}', [VariantAttributeController::class, 'update'])->name('attributes.update');
            Route::post('attributes/{attribute}/values', [VariantAttributeController::class, 'storeValue'])->name('attributes.values.store');
            Route::put('attribute-values/{value}', [VariantAttributeController::class, 'updateValue'])->name('attributes.values.update');
            Route::delete('attribute-values/{value}', [VariantAttributeController::class, 'destroyValue'])->name('attributes.values.destroy');

            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
            Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
            Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        });

        Route::get('demo-data', [DemoDataController::class, 'index'])->name('demo-data.index');
        Route::post('demo-data', [DemoDataController::class, 'store'])->middleware('throttle:3,1')->name('demo-data.store');
        Route::delete('demo-data', [DemoDataController::class, 'destroy'])->middleware('throttle:3,1')->name('demo-data.destroy');

        Route::middleware('can:users.manage')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });
    });
});

require __DIR__.'/settings.php';
