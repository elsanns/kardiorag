<?php

namespace Tests\Unit;

use App\Services\Rag\Embedder;
use PHPUnit\Framework\TestCase;

class EmbedderTest extends TestCase
{
    public function test_to_literal_formats_a_pgvector_string(): void
    {
        $this->assertSame('[0.1,0.2,0.3]', Embedder::toLiteral([0.1, 0.2, 0.3]));
    }

    public function test_to_literal_casts_values_to_float(): void
    {
        $this->assertSame('[1,2,3]', Embedder::toLiteral(['1', '2', '3']));
    }
}
