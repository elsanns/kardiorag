<?php

namespace App\Jobs;

use App\Services\OpenFda\Ingestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued ingestion of one drug. Dispatched per-drug so ingestion is a background
 * workflow; can be run inline via dispatchSync for the demo (no worker required).
 *
 * ShouldBeUnique keyed on the drug name prevents two ingestions of the same drug
 * from running at once (re-fetch + delete + re-embed is not safe to run concurrently).
 */
class IngestDrugJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public int $tries = 2;

    public function __construct(public string $generic) {}

    public function uniqueId(): string
    {
        return $this->generic;
    }

    public function handle(Ingestor $ingestor): void
    {
        $ingestor->ingestDrug($this->generic);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('Drug ingestion failed', [
            'drug' => $this->generic,
            'error' => $e?->getMessage(),
        ]);
    }
}
