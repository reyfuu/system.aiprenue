<?php

use App\Http\Controllers\AksesController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InsightController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MindmapController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PembukuanController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ScriptController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureMenuAccess;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Registrasi mandiri — pendaftar langsung aktif sebagai 'staff'.
// throttle: pintu ini membuat baris user dari internet tanpa gerbang apa pun,
// jadi dibatasi 6 percobaan/menit per IP supaya tak bisa dibanjiri skrip.
Route::middleware('throttle:6,1')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

// Lupa password — user memasang passwordnya sendiri lewat tautan di email.
// Nama route `password.reset` WAJIB persis begitu: notifikasi bawaan Laravel
// membangun URL tautannya dengan route('password.reset', ...). Ganti namanya =
// email terkirim tapi tautannya mati.
// throttle: pintu ini mengirim email & bisa dipakai menebak-nebak alamat,
// jadi dibatasi 6 permintaan/menit per IP.
Route::middleware('throttle:6,1')->group(function () {
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::get('/', fn () => redirect()->route(auth()->check() ? auth()->user()->homeRoute() : 'login'));

// Pipeline (butuh login) + batasan akses per role
Route::middleware(['auth', EnsureMenuAccess::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/pipelines', [PipelineController::class, 'index'])->name('pipelines.index');
    Route::get('/pipelines/kanban', [PipelineController::class, 'kanban'])->name('pipelines.kanban');
    // Isi & urutan satu kolom kanban sesudah drag. Per-KOLOM, bukan per-kartu:
    // satu kiriman memuat pindah antar kolom sekaligus geser naik/turun.
    Route::patch('/pipelines/reorder', [PipelineController::class, 'reorder'])->name('pipelines.reorder');
    Route::patch('/pipelines/{pipeline}/done', [PipelineController::class, 'updateDone'])->name('pipelines.done');
    Route::patch('/pipelines/{pipeline}/archive', [PipelineController::class, 'archive'])->name('pipelines.archive');

    // Komentar kartu — boleh semua yg akses kanban (staff yg ditugasi pun bisa)
    Route::post('/pipelines/{pipeline}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // Lampiran kartu — hanya super admin/IT (dibatasi EnsureMenuAccess)
    Route::post('/pipelines/{pipeline}/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Kelola definisi label kartu — gate owner-only ada di LabelController.
    Route::post('/labels', [LabelController::class, 'store'])->name('labels.store');
    Route::put('/labels/{label}', [LabelController::class, 'update'])->name('labels.update');
    Route::delete('/labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');

    // Board (kategori) — CRUD hanya super admin & IT
    Route::post('/boards', [BoardController::class, 'store'])->name('boards.store');
    Route::put('/boards/{board}', [BoardController::class, 'update'])->name('boards.update');
    Route::delete('/boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');

    // Kolom kanban (list) — CRUD hanya super admin & IT
    // Urutan kolom sesudah drag. Satu kiriman = seluruh kolom board.
    // Nama `columns.*` sudah diborong EnsureMenuAccess (isManageRoute + menusFor),
    // jadi rute ini ikut terjaga tanpa perlu didaftarkan di sana satu per satu.
    Route::patch('/columns/reorder', [ColumnController::class, 'reorder'])->name('columns.reorder');
    Route::post('/columns', [ColumnController::class, 'store'])->name('columns.store');
    Route::put('/columns/{column}', [ColumnController::class, 'update'])->name('columns.update');
    Route::delete('/columns/{column}', [ColumnController::class, 'destroy'])->name('columns.destroy');
    Route::post('/pipelines', [PipelineController::class, 'store'])->name('pipelines.store');
    Route::put('/pipelines/{pipeline}', [PipelineController::class, 'update'])->name('pipelines.update');
    Route::delete('/pipelines/{pipeline}', [PipelineController::class, 'destroy'])->name('pipelines.destroy');

    // Order (pesanan) — tabel + CRUD modal. Mutasi dibatasi EnsureMenuAccess.
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');

    // Mindmap (mind-elixir) — galeri + editor + simpan/hapus
    Route::get('/mindmaps', [MindmapController::class, 'index'])->name('mindmaps.index');
    Route::post('/mindmaps', [MindmapController::class, 'store'])->name('mindmaps.store');
    Route::get('/mindmaps/{mindmap}', [MindmapController::class, 'show'])->name('mindmaps.show');
    Route::put('/mindmaps/{mindmap}', [MindmapController::class, 'update'])->name('mindmaps.update');
    Route::delete('/mindmaps/{mindmap}', [MindmapController::class, 'destroy'])->name('mindmaps.destroy');

    // Script — isinya dikirim agen Daily Script Rave lewat POST /api/scripts (routes/api.php)
    Route::get('/script', [ScriptController::class, 'index'])->name('script.index');
    // Unduh satu paket sebagai PDF. Ditulis sebelum /script/{brand} supaya tanggal
    // tidak ditangkap sebagai brand oleh router.
    Route::get('/script/{brand}/{date}/pdf', [ScriptController::class, 'pdf'])->name('script.pdf');
    Route::post('/script/{brand}/upload', [ScriptController::class, 'upload'])->name('script.upload');
    Route::get('/script/{brand}', [ScriptController::class, 'show'])->name('script.show');
    // Insight — performa konten Instagram & YouTube. Datanya dikirim agen luar
    // lewat POST /api/insights (belum ada; lihat docs/insight-instagram-youtube.md).
    Route::get('/insight', [InsightController::class, 'index'])->name('insight.index');

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

    // Manajemen Akses — centang menu per peran. Dijaga EnsureMenuAccess:
    // menu 'akses' (owner/manager/it) + akses.update butuh canManage.
    Route::get('/akses', [AksesController::class, 'index'])->name('akses.index');
    Route::put('/akses', [AksesController::class, 'update'])->name('akses.update');
});
