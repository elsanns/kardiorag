<?php

namespace Tests\Unit;

use App\Services\Rag\Chunker;
use PHPUnit\Framework\TestCase;

class ChunkerTest extends TestCase
{
    public function test_empty_text_yields_no_chunks(): void
    {
        $this->assertSame([], (new Chunker())->split('   '));
    }

    public function test_short_text_is_a_single_chunk(): void
    {
        $chunks = (new Chunker(size: 900))->split('Amiodarone is contraindicated in cardiogenic shock.');
        $this->assertCount(1, $chunks);
        $this->assertStringContainsString('cardiogenic shock', $chunks[0]);
    }

    public function test_long_text_splits_into_bounded_chunks(): void
    {
        $text = str_repeat('This is a clinical sentence about the drug. ', 80); // ~3500 chars
        $chunks = (new Chunker(size: 900, overlap: 150))->split($text);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $c) {
            $this->assertNotSame('', $c);
            $this->assertLessThanOrEqual(900, mb_strlen($c));
        }
    }
}
