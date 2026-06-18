<?php

namespace App\Services\OpenFda;

use App\Models\AuditLog;
use App\Models\Document;
use App\Services\Rag\Chunker;
use App\Services\Rag\Embedder;
use Illuminate\Support\Facades\DB;

/**
 * Ingestion pipeline: openFDA fetch -> normalize -> upsert documents -> chunk -> embed.
 * Idempotent per (source_id, field): re-running refreshes content and re-embeds.
 */
class Ingestor
{
    public function __construct(
        private OpenFdaClient $client,
        private Chunker $chunker,
        private Embedder $embedder,
    ) {}

    /**
     * @return array{drug:string,documents:int,chunks:int,skipped:bool}
     */
    public function ingestDrug(string $generic): array
    {
        $records = $this->client->fetchDrugRecords($generic);

        if (empty($records)) {
            return ['drug' => $generic, 'documents' => 0, 'chunks' => 0, 'skipped' => true];
        }

        $docCount = 0;
        $chunkCount = 0;

        foreach ($records as $rec) {
            $result = DB::transaction(function () use ($rec) {
                $document = Document::updateOrCreate(
                    ['source' => 'openfda', 'source_id' => $rec['source_id'], 'field' => $rec['field']],
                    [
                        'drug_generic' => $rec['drug_generic'],
                        'drug_brand'   => $rec['drug_brand'],
                        'title'        => $rec['title'],
                        'url'          => $rec['url'],
                        'content'      => $rec['content'],
                        'meta'         => ['ingested_at' => now()->toIso8601String()],
                    ]
                );

                // Refresh chunks for this document.
                $document->chunks()->delete();

                $pieces = $this->chunker->split($rec['content']);
                $chunks = [];
                foreach ($pieces as $i => $piece) {
                    $chunks[] = $document->chunks()->create([
                        'ord'        => $i,
                        'content'    => $piece,
                        'char_count' => mb_strlen($piece),
                    ]);
                }

                return [$document, $chunks];
            });

            [, $chunks] = $result;
            $docCount++;

            // Embedding hits the model server; keep it outside the DB transaction.
            $chunkCount += $this->embedder->embedChunks($chunks);
        }

        AuditLog::record('ingest.drug', [
            'resource_type' => 'drug',
            'resource_id'   => $generic,
            'meta'          => ['documents' => $docCount, 'chunks' => $chunkCount],
        ]);

        return ['drug' => $generic, 'documents' => $docCount, 'chunks' => $chunkCount, 'skipped' => false];
    }
}
