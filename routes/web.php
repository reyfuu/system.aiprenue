<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PembukuanController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureMenuAccess;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', fn () => redirect()->route(auth()->check() ? auth()->user()->homeRoute() : 'login'));

// Pipeline (butuh login) + batasan akses per role
Route::middleware(['auth', EnsureMenuAccess::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/pipelines', [PipelineController::class, 'index'])->name('pipelines.index');
    Route::get('/pipelines/kanban', [PipelineController::class, 'kanban'])->name('pipelines.kanban');
    Route::patch('/pipelines/{pipeline}/progress', [PipelineController::class, 'updateProgress'])->name('pipelines.progress');
    Route::patch('/pipelines/{pipeline}/todos', [PipelineController::class, 'updateTodos'])->name('pipelines.todos');
    Route::patch('/pipelines/{pipeline}/archive', [PipelineController::class, 'archive'])->name('pipelines.archive');

    // Komentar kartu — boleh semua yg akses kanban (staff yg ditugasi pun bisa)
    Route::post('/pipelines/{pipeline}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // Lampiran kartu — hanya super admin/IT (dibatasi EnsureMenuAccess)
    Route::post('/pipelines/{pipeline}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Board (kategori) — CRUD hanya super admin & IT
    Route::post('/boards', [BoardController::class, 'store'])->name('boards.store');
    Route::put('/boards/{board}', [BoardController::class, 'update'])->name('boards.update');
    Route::delete('/boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');

    // Kolom kanban (list) — CRUD hanya super admin & IT
    Route::post('/columns', [ColumnController::class, 'store'])->name('columns.store');
    Route::put('/columns/{column}', [ColumnController::class, 'update'])->name('columns.update');
    Route::delete('/columns/{column}', [ColumnController::class, 'destroy'])->name('columns.destroy');
    Route::get('/pipelines/report', [PipelineController::class, 'report'])->name('pipelines.report');
    Route::post('/pipelines', [PipelineController::class, 'store'])->name('pipelines.store');
    Route::put('/pipelines/{pipeline}', [PipelineController::class, 'update'])->name('pipelines.update');
    Route::delete('/pipelines/{pipeline}', [PipelineController::class, 'destroy'])->name('pipelines.destroy');

    // Script (template dulu — data dikirim Hermes agent nanti)
    Route::get('/script', fn () => Inertia::render('Script'))->name('script.index');

    // Pembukuan (rekap keuangan)
    Route::get('/pembukuan', [PembukuanController::class, 'index'])->name('pembukuan.index');
    Route::get('/pembukuan/report', [PembukuanController::class, 'report'])->name('pembukuan.report');

    // CRUD transaksi & inventaris pembukuan (super admin/IT)
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::post('/inventories', [InventoryController::class, 'store'])->name('inventories.store');
    Route::put('/inventories/{inventory}', [InventoryController::class, 'update'])->name('inventories.update');
    Route::delete('/inventories/{inventory}', [InventoryController::class, 'destroy'])->name('inventories.destroy');

    // User management (CRUD)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
