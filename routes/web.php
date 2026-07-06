<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PipelineController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', fn () => redirect()->route('pipelines.index'));

// Pipeline (butuh login)
Route::middleware('auth')->group(function () {
    Route::get('/pipelines', [PipelineController::class, 'index'])->name('pipelines.index');
    Route::get('/pipelines/report', [PipelineController::class, 'report'])->name('pipelines.report');
    Route::post('/pipelines', [PipelineController::class, 'store'])->name('pipelines.store');
    Route::put('/pipelines/{pipeline}', [PipelineController::class, 'update'])->name('pipelines.update');
    Route::delete('/pipelines/{pipeline}', [PipelineController::class, 'destroy'])->name('pipelines.destroy');
});
