<?php

namespace App\Jobs;

use App\Models\Query;
use App\Services\Rag\RagService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs retrieval + generation for a pending query off the request cycle, so the
 * (slow, on CPU) local model never blocks the HTTP request. The web UI polls the
 * query status until this job finishes.
 */
class AnswerQuestionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $queryId) {}

    public function handle(RagService $rag): void
    {
        $query = Query::find($this->queryId);
        if ($query && $query->status === 'pending') {
            $rag->runQuery($query);
        }
    }

    /**
     * Mark the query as failed if the job dies outside RagService's own try/catch
     * (e.g. a worker timeout), so the polling UI stops waiting instead of hanging.
     */
    public function failed(?Throwable $e): void
    {
        $query = Query::find($this->queryId);
        if ($query && in_array($query->status, ['pending', 'processing'], true)) {
            $query->update([
                'status' => 'failed',
                'error' => $e?->getMessage() ?? 'Job failed.',
            ]);
        }
    }
}
