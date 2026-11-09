<?php

use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\DonorController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\StockAlertController;
use App\Http\Controllers\FrontendController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', [FrontendController::class, 'home'])->name('home');
Route::get('/workspace', [FrontendController::class, 'workspace'])->name('workspace');
Route::redirect('/connected.html', '/workspace');

Route::get('/{directory}/{file}', [FrontendController::class, 'asset'])
    ->where('directory', 'css|src|assets/images')
    ->where('file', '[A-Za-z0-9_ .%-]+');

Route::prefix('api/v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', function () {
        try {
            DB::select('SELECT 1');

            return response()->json(['status' => 'ok']);
        } catch (Throwable) {
            return response()->json(['status' => 'unavailable'], 503);
        }
    })->middleware('throttle:30,1')->name('health');

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:staff-login')->name('login');
    Route::middleware(['auth:sanctum', 'active', 'throttle:staff-api'])->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::get('/donors', [DonorController::class, 'index'])->name('donors.index');
        Route::post('/donors', [DonorController::class, 'store'])->name('donors.store');
        Route::get('/donors/{donor}', [DonorController::class, 'show'])->whereUuid('donor')->name('donors.show');
        Route::patch('/donors/{donor}', [DonorController::class, 'update'])->whereUuid('donor')->name('donors.update');
        Route::get('/audit-logs', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/assignments', [AssignmentController::class, 'index']);
        Route::get('/assignments/{donor}', [AssignmentController::class, 'show'])->whereUuid('donor');
        Route::put('/assignments/{donor}', [AssignmentController::class, 'store'])->whereUuid('donor');
        Route::get('/donors/{donor}/donations', [DonationController::class, 'index'])->whereUuid('donor');
        Route::post('/donors/{donor}/donations', [DonationController::class, 'store'])->whereUuid('donor');
        Route::patch('/donors/{donor}/donations/{donation}', [DonationController::class, 'update'])->whereUuid('donor')->whereUuid('donation');
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::post('/inventory', [InventoryController::class, 'store']);
        Route::get('/inventory/{unit}', [InventoryController::class, 'show'])->whereUuid('unit');
        Route::post('/inventory/{unit}/transitions', [InventoryController::class, 'transition'])->whereUuid('unit');
        Route::get('/stock-rules', [StockAlertController::class, 'rules']);
        Route::post('/stock-rules', [StockAlertController::class, 'saveRule']);
        Route::get('/stock-alerts', [StockAlertController::class, 'index']);
        Route::post('/stock-alerts/reconcile', [StockAlertController::class, 'reconcile']);
        Route::post('/stock-alerts/{alert}/acknowledge', [StockAlertController::class, 'acknowledge'])->whereNumber('alert');
    });
});
