<?php

use App\Http\Controllers\Api\InsightIngestController;
use App\Http\Controllers\Api\ScriptIngestController;
use App\Http\Controllers\Api\TaskIngestController;
use Illuminate\Support\Facades\Route;

// Pintu masuk agen Daily Script Rave. Sengaja di luar routes/web.php: tak perlu
// sesi/CSRF & tak lewat EnsureMenuAccess — gerbangnya bearer token (SCRIPT_AGENT_TOKEN).
// throttle: agen normalnya memanggil 2x/hari, jadi 30/menit sudah sangat longgar
// & sekaligus menutup upaya menebak token secara membabi buta.
Route::post('/scripts', [ScriptIngestController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('api.scripts.store');

// Pintu masuk agen Insight (cron di VPS) → metrik Instagram & YouTube.
// Sama polanya dengan /scripts: di luar web.php, gerbangnya bearer token
// (INSIGHT_AGENT_TOKEN). throttle longgar tapi menutup tebakan token brute force.
Route::post('/insights', [InsightIngestController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('api.insights.store');

// Pintu masuk "buat tugas dari AI" (MCP Claude/Gemini, Custom GPT ChatGPT) → kartu Kanban.
// Gerbang bearer token (TASK_AGENT_TOKEN). throttle longgar utk pemakaian owner.
Route::get('/boards', [TaskIngestController::class, 'boards'])->middleware('throttle:60,1')->name('api.boards');
Route::post('/tasks', [TaskIngestController::class, 'store'])->middleware('throttle:60,1')->name('api.tasks.store');
