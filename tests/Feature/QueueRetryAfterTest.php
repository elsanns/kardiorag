<?php

namespace Tests\Feature;

use App\Jobs\AnswerQuestionJob;
use App\Jobs\IngestDrugJob;
use Tests\TestCase;

class QueueRetryAfterTest extends TestCase
{
    /**
     * The database queue `retry_after` must exceed the longest job timeout, otherwise a
     * slow job (CPU generation routinely runs > 90s) is re-released and processed twice.
     */
    public function test_database_queue_retry_after_exceeds_longest_job_timeout(): void
    {
        $retryAfter = (int) config('queue.connections.database.retry_after');

        $longestTimeout = max(
            (new AnswerQuestionJob(1))->timeout,
            (new IngestDrugJob('amiodarone'))->timeout,
        );

        $this->assertGreaterThan(
            $longestTimeout,
            $retryAfter,
            'queue retry_after must be greater than the longest job timeout to avoid duplicate execution.',
        );
    }
}
