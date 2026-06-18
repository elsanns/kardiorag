<?php

namespace Tests\Feature\Api;

use App\Jobs\IngestDrugJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IngestTest extends TestCase
{
    use RefreshDatabase;

    public function test_curated_drug_queues_ingestion_and_audits(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/ingest', ['drug' => 'amiodarone'])
            ->assertStatus(202)
            ->assertJsonPath('data.drug', 'amiodarone')
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonStructure(['meta' => ['poll', 'disclaimer']]);

        Queue::assertPushed(IngestDrugJob::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'api.ingest', 'resource_id' => 'amiodarone']);
    }

    public function test_non_curated_drug_is_rejected_with_allowed_list(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/ingest', ['drug' => 'aspirin'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'drug_not_allowed')
            ->assertJsonStructure(['error' => ['allowed']]);

        Queue::assertNothingPushed();
    }

    public function test_missing_drug_field_is_a_validation_error(): void
    {
        $this->postJson('/api/v1/ingest', [])->assertStatus(422);
    }
}
