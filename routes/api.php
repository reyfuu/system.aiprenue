<?php

use App\Http\Controllers\Api\ScriptIngestController;
use Illuminate\Support\Facades\Route;

// Pintu masuk agen Daily Script Rave. Sengaja di luar routes/web.php: tak perlu
// sesi/CSRF & tak lewat EnsureMenuAccess — gerbangnya bearer token (SCRIPT_AGENT_TOKEN).
// throttle: agen normalnya memanggil 2x/hari, jadi 30/menit sudah sangat longgar
// & sekaligus menutup upaya menebak token secara membabi buta.
Route::post('/scripts', [ScriptIngestController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('api.scripts.store');
