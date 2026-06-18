<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrugsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_knowledge_base_returns_no_drugs(): void
    {
        $this->getJson('/api/v1/drugs')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonStructure(['data', 'meta' => ['total', 'disclaimer']]);
    }

    public function test_ingested_drug_appears_with_counts(): void
    {
        $doc = Document::create([
            'source' => 'openfda', 'source_id' => 'set-1', 'drug_generic' => 'amiodarone',
            'drug_brand' => 'Pacerone', 'field' => 'contraindications', 'title' => 'Contraindications',
            'content' => 'Cardiogenic shock.',
        ]);
        $doc->chunks()->create(['ord' => 0, 'content' => 'Cardiogenic shock.', 'char_count' => 18]);

        $this->getJson('/api/v1/drugs')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.drug', 'amiodarone')
            ->assertJsonPath('data.0.documents', 1)
            ->assertJsonPath('data.0.chunks', 1);
    }
}
