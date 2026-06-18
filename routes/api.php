<?php

use App\Http\Controllers\Api\DrugController;
use App\Http\Controllers\Api\IngestController;
use Illuminate\Support\Facades\Route;

/*
 | KardioRAG public API (v1). No authentication in this demo (documented); rate-limited.
 | Mounted under the /api/v1 prefix (see bootstrap/app.php).
 */

// Import: trigger openFDA ingestion of a curated drug (queued).
Route::post('/ingest', [IngestController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('api.ingest');

// Read: ingested drugs + document/chunk counts (makes import observable).
Route::get('/drugs', [DrugController::class, 'index'])->name('api.drugs.index');
