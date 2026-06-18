<?php

namespace App\Services\Rag;

/**
 * Prompt-injection guardrails for the RAG flow.
 *
 *  - flagInput():     (c) detect obvious instruction-override phrasing in the user question.
 *                     We flag + audit-log it; we do NOT silently drop or alter the question.
 *  - checkGrounding(): (b) verify the model's answer is grounded in the provided sources
 *                     (cites at least one valid source, or is a legitimate "no info" refusal).
 *                     Ungrounded answers are replaced with a safe fallback by the caller.
 */
class PromptGuard
{
    /** High-signal user-side injection markers (case-insensitive). */
    private const INPUT_PATTERNS = [
        '/ignore\s+(all\s+|the\s+)?(previous|prior|above)\s+(instructions|prompts?)/i',
        '/disregard\s+(all\s+|the\s+)?(previous|prior|above|instructions)/i',
        '/(reveal|show|print|repeat|leak)\s+(your\s+)?(system\s+)?(prompt|instructions)/i',
        '/forget\s+(everything|all|the|previous)/i',
        '/you\s+are\s+now\b/i',
        '/override\s+(the\s+)?(system|instructions|rules)/i',
        '/jailbreak/i',
    ];

    /** Legitimate "I don't have that" refusal — counts as grounded (safe). */
    private const REFUSAL_PATTERN =
        '/(don\'?t|do not)\s+have\s+(that\s+)?information|not\s+(contained|found|available)\s+in\s+the\s+sources|no\s+(relevant\s+)?information/i';

    /** @return list<string> matched indicators (empty = clean) */
    public function flagInput(string $question): array
    {
        $hits = [];
        foreach (self::INPUT_PATTERNS as $pattern) {
            if (preg_match($pattern, $question, $m)) {
                $hits[] = trim($m[0]);
            }
        }

        return $hits;
    }

    /**
     * @return array{grounded: bool, cited: list<int>, hallucinated: list<int>, refusal: bool}
     */
    public function checkGrounding(string $answer, int $sourceCount): array
    {
        preg_match_all('/\[(\d{1,2})\]/', $answer, $m);
        $citations = array_map('intval', $m[1]);

        $cited = array_values(array_unique(array_filter(
            $citations, fn ($n) => $n >= 1 && $n <= $sourceCount
        )));
        $hallucinated = array_values(array_unique(array_filter(
            $citations, fn ($n) => $n < 1 || $n > $sourceCount
        )));

        $refusal = (bool) preg_match(self::REFUSAL_PATTERN, $answer);

        return [
            'grounded'     => ! empty($cited) || $refusal,
            'cited'        => $cited,
            'hallucinated' => $hallucinated,
            'refusal'      => $refusal,
        ];
    }

    public function safeFallback(): string
    {
        return "I can only answer from the cited sources, and I couldn't ground a reliable "
             . "answer to that question in them. Please rephrase, or ask about a drug/topic "
             . "that has been loaded.";
    }
}
