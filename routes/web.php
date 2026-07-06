<?php

use App\Http\Controllers\PipelineController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('pipelines.index'));

Route::get('/pipelines', [PipelineController::class, 'index'])->name('pipelines.index');
Route::post('/pipelines', [PipelineController::class, 'store'])->name('pipelines.store');
Route::put('/pipelines/{pipeline}', [PipelineController::class, 'update'])->name('pipelines.update');
Route::delete('/pipelines/{pipeline}', [PipelineController::class, 'destroy'])->name('pipelines.destroy');
