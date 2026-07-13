<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PembukuanController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', fn () => redirect()->route('dashboard'));

// Pipeline (butuh login)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/pipelines', [PipelineController::class, 'index'])->name('pipelines.index');
    Route::get('/pipelines/kanban', [PipelineController::class, 'kanban'])->name('pipelines.kanban');
    Route::patch('/pipelines/{pipeline}/progress', [PipelineController::class, 'updateProgress'])->name('pipelines.progress');
    Route::get('/pipelines/report', [PipelineController::class, 'report'])->name('pipelines.report');
    Route::post('/pipelines', [PipelineController::class, 'store'])->name('pipelines.store');
    Route::put('/pipelines/{pipeline}', [PipelineController::class, 'update'])->name('pipelines.update');
    Route::delete('/pipelines/{pipeline}', [PipelineController::class, 'destroy'])->name('pipelines.destroy');

    // Script (template dulu — data dikirim Hermes agent nanti)
    Route::view('/script', 'script.index')->name('script.index');

    // Pembukuan (rekap keuangan)
    Route::get('/pembukuan', [PembukuanController::class, 'index'])->name('pembukuan.index');
    Route::get('/pembukuan/report', [PembukuanController::class, 'report'])->name('pembukuan.report');

    // User management (CRUD)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
