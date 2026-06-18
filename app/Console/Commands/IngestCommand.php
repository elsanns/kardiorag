<?php

namespace App\Console\Commands;

use App\Jobs\IngestDrugJob;
use App\Services\OpenFda\Ingestor;
use Illuminate\Console\Command;

class IngestCommand extends Command
{
    protected $signature = 'kardiorag:ingest
        {drugs?* : Generic drug names (defaults to the curated cardiology set)}
        {--queue : Dispatch jobs to the queue instead of running inline}';

    protected $description = 'Ingest openFDA drug labels into the RAG knowledge base';

    public function handle(Ingestor $ingestor): int
    {
        $drugs = $this->argument('drugs') ?: config('kardiorag.openfda.default_drugs');

        $this->info('Ingesting ' . count($drugs) . ' drug(s): ' . implode(', ', $drugs));

        if ($this->option('queue')) {
            foreach ($drugs as $drug) {
                IngestDrugJob::dispatch($drug);
            }
            $this->info('Dispatched ' . count($drugs) . ' ingestion job(s) to the queue.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($drugs as $drug) {
            $this->line("  • {$drug} ...");
            $stats = $ingestor->ingestDrug($drug);
            $rows[] = [
                $drug,
                $stats['skipped'] ? 'not found' : 'ok',
                $stats['documents'],
                $stats['chunks'],
            ];
        }

        $this->newLine();
        $this->table(['Drug', 'Status', 'Documents', 'Chunks'], $rows);

        return self::SUCCESS;
    }
}
