<?php

namespace App\Jobs;

use App\Services\OpenFda\Ingestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued ingestion of one drug. Dispatched per-drug so ingestion is a background
 * workflow; can be run inline via dispatchSync for the demo (no worker required).
 */
class IngestDrugJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;
    public int $tries = 2;

    public function __construct(public string $generic) {}

    public function handle(Ingestor $ingestor): void
    {
        $ingestor->ingestDrug($this->generic);
    }
}
