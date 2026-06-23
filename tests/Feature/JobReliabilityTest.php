<?php

namespace Tests\Feature;

use App\Jobs\AnswerQuestionJob;
use App\Jobs\IngestDrugJob;
use App\Models\Query;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class JobReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_answer_job_failed_marks_pending_or_processing_query_failed(): void
    {
        $query = Query::create([
            'question' => 'What are the contraindications of amiodarone?',
            'status' => 'processing',
        ]);

        (new AnswerQuestionJob($query->id))->failed(new RuntimeException('worker timeout'));

        $query->refresh();
        $this->assertSame('failed', $query->status);
        $this->assertSame('worker timeout', $query->error);
    }

    public function test_answer_job_failed_does_not_overwrite_a_completed_query(): void
    {
        $query = Query::create([
            'question' => 'What drugs interact with digoxin?',
            'status' => 'done',
            'answer' => 'an answer',
        ]);

        (new AnswerQuestionJob($query->id))->failed(new RuntimeException('late failure'));

        $this->assertSame('done', $query->refresh()->status);
    }

    public function test_ingest_job_is_unique_per_drug(): void
    {
        $job = new IngestDrugJob('amiodarone');

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('amiodarone', $job->uniqueId());
    }
}
