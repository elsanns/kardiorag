<?php

namespace App\Services\Rag;

/**
 * Deterministic, character-based splitter with overlap. Tries to break on sentence
 * boundaries near the target size so chunks stay coherent for retrieval.
 */
class Chunker
{
    public function __construct(private int $size = 900, private int $overlap = 150) {}

    /** @return list<string> */
    public function split(string $text): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) <= $this->size) {
            return [$text];
        }

        $chunks = [];
        $start  = 0;
        $len    = mb_strlen($text);

        while ($start < $len) {
            $end = min($start + $this->size, $len);

            // Prefer to cut at the last sentence end within the window.
            if ($end < $len) {
                $window = mb_substr($text, $start, $end - $start);
                if (preg_match('/.*[.!?]\s/s', $window, $m)) {
                    $end = $start + mb_strlen($m[0]);
                }
            }

            $piece = trim(mb_substr($text, $start, $end - $start));
            if ($piece !== '') {
                $chunks[] = $piece;
            }

            if ($end >= $len) {
                break;
            }
            $start = max($end - $this->overlap, $start + 1);
        }

        return $chunks;
    }
}
