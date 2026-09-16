<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| moringrainERP 路由
|--------------------------------------------------------------------------
| 轻量级跨境电商 ERP · 订单管理 MVP
*/

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
    Route::post('/simulate', [OrderController::class, 'simulate'])->name('simulate');
    Route::get('/{order}', [OrderController::class, 'show'])->name('show');
    Route::get('/{order}/edit', [OrderController::class, 'edit'])->name('edit');
    Route::put('/{order}', [OrderController::class, 'update'])->name('update');
    Route::delete('/{order}', [OrderController::class, 'destroy'])->name('destroy');
    Route::post('/{order}/status', [OrderController::class, 'updateStatus'])->name('status');
    Route::post('/{order}/ship', [OrderController::class, 'ship'])->name('ship');
});

// ---- 基础资料 ----
Route::resource('shops', ShopController::class)->except(['show']);
Route::resource('products', ProductController::class)->except(['show']);

// ---- 报表 ----
Route::get('reports/profit', [ReportController::class, 'profit'])->name('reports.profit');
