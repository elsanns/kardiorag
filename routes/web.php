<?php

use App\Http\Controllers\AskController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AskController::class, 'index'])->name('ask.index');

// Ask endpoints (same-origin JSON, used by the page's fetch()).
Route::post('/ask', [AskController::class, 'submit'])
    ->middleware('throttle:ask')
    ->name('ask.submit');
Route::get('/ask/{query}/status', [AskController::class, 'status'])->name('ask.status');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
