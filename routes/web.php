<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| mornrainERP 路由
|--------------------------------------------------------------------------
| 轻量级跨境电商 ERP
|  · /login 与 /logout 之外，全部路由需要登录
|  · 基础资料（店铺/商品/平台对接）与账号管理额外要求管理员角色
*/

// ---- 登录 / 登出 ----
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ---- 后台（需登录） ----
Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ---- 订单管理 ----
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/create', [OrderController::class, 'create'])->name('create');
        Route::post('/', [OrderController::class, 'store'])->name('store');
        Route::get('/import', [OrderController::class, 'importForm'])->name('import.form');
        Route::post('/import', [OrderController::class, 'import'])->name('import');
        Route::get('/export', [OrderController::class, 'export'])->name('export');
        Route::post('/batch-ship', [OrderController::class, 'batchShip'])->name('batch-ship');
        Route::get('/{order}', [OrderController::class, 'show'])->name('show');
        Route::get('/{order}/edit', [OrderController::class, 'edit'])->name('edit');
        Route::put('/{order}', [OrderController::class, 'update'])->name('update');
        Route::delete('/{order}', [OrderController::class, 'destroy'])->name('destroy');
        Route::post('/{order}/status', [OrderController::class, 'updateStatus'])->name('status');
        Route::post('/{order}/ship', [OrderController::class, 'ship'])->name('ship');
    });

    // ---- 库存与采购 ----
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::post('/{product}/adjust', [InventoryController::class, 'adjust'])->name('adjust');
    });

    // ---- 采购管理（管理员） ----
    Route::prefix('purchases')->name('purchases.')->middleware('admin')->group(function () {
        Route::get('/', [PurchaseController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseController::class, 'create'])->name('create');
        Route::post('/', [PurchaseController::class, 'store'])->name('store');
        Route::post('/from-suggestions', [PurchaseController::class, 'fromSuggestions'])->name('from-suggestions');
        Route::get('/{purchaseOrder}', [PurchaseController::class, 'show'])->name('show');
        Route::get('/{purchaseOrder}/edit', [PurchaseController::class, 'edit'])->name('edit');
        Route::put('/{purchaseOrder}', [PurchaseController::class, 'update'])->name('update');
        Route::delete('/{purchaseOrder}', [PurchaseController::class, 'destroy'])->name('destroy');
        Route::post('/{purchaseOrder}/receive', [PurchaseController::class, 'receive'])->name('receive');
        Route::post('/{purchaseOrder}/place', [PurchaseController::class, 'place'])->name('place');
        Route::post('/{purchaseOrder}/cancel', [PurchaseController::class, 'cancel'])->name('cancel');
    });

    // ---- 物流轨迹回写 ----
    Route::prefix('shipments')->name('shipments.')->group(function () {
        Route::post('/track-all', [ShipmentController::class, 'trackAll'])->name('track-all');
        Route::post('/{shipment}/track', [ShipmentController::class, 'track'])->name('track');
    });

    // ---- 平台对接（自动拉单） ----
    Route::prefix('sync')->name('sync.')->middleware('admin')->group(function () {
        Route::get('/', [SyncController::class, 'index'])->name('index');
        Route::put('/{shop}/config', [SyncController::class, 'saveConfig'])->name('config');
        Route::post('/{shop}/run', [SyncController::class, 'run'])->name('run');
        Route::post('/run-all', [SyncController::class, 'runAll'])->name('run-all');
    });

    // ---- 基础资料（管理员） ----
    Route::resource('shops', ShopController::class)->except(['show'])->middleware('admin');
    Route::resource('products', ProductController::class)->except(['show'])->middleware('admin');
    Route::resource('suppliers', SupplierController::class)->except(['show'])->middleware('admin');
    Route::post('suppliers/{supplier}/toggle', [SupplierController::class, 'toggle'])
        ->middleware('admin')
        ->name('suppliers.toggle');

    // ---- 报表 ----
    Route::get('reports/profit', [ReportController::class, 'profit'])->name('reports.profit');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    // ---- 账号与密码 ----
    Route::get('/password', [AuthController::class, 'passwordForm'])->name('password.form');
    Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');

    Route::prefix('users')->name('users.')->middleware('admin')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::post('/{user}/toggle', [UserController::class, 'toggle'])->name('toggle');
        Route::put('/{user}/password', [UserController::class, 'resetPassword'])->name('password');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    // ---- 操作审计（管理员） ----
    Route::prefix('audit')->name('audit.')->middleware('admin')->group(function () {
        Route::get('/', [AuditController::class, 'index'])->name('index');
    });
});
