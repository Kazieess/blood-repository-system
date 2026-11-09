<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DonorController;
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
    });
});
