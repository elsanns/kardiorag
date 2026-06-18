<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\DB;

/**
 * Vector retrieval over pgvector. Uses cosine distance (<=>) with an optional
 * entity boost: if the question mentions a known drug, matching that drug's chunks
 * are preferred.
 */
class Retriever
{
    public function __construct(private Embedder $embedder) {}

    /**
     * @return list<array{id:int,document_id:int,content:string,distance:float,drug_generic:?string,drug_brand:?string,field:?string,title:?string,url:?string}>
     */
    public function retrieve(string $question, ?int $topK = null): array
    {
        $topK    = $topK ?? (int) config('kardiorag.retrieval.top_k', 4);
        $literal = $this->embedder->embedToLiteral($question);
        $drugs   = $this->mentionedDrugs($question);

        // Entity boost: subtract a small bonus from the distance for matching drugs,
        // so a clearly-named drug wins ties against generic field similarity.
        $boostSql = '0';
        if (! empty($drugs)) {
            $placeholders = implode(',', array_fill(0, count($drugs), '?'));
            $boostSql = "CASE WHEN d.drug_generic IN ($placeholders) THEN 0.15 ELSE 0 END";
        }

        $sql = "
            SELECT c.id, c.document_id, c.content,
                   (c.embedding <=> ?::vector) AS distance,
                   (c.embedding <=> ?::vector) - $boostSql AS score,
                   d.drug_generic, d.drug_brand, d.field, d.title, d.url
            FROM chunks c
            JOIN documents d ON d.id = c.document_id
            WHERE c.embedding IS NOT NULL
            ORDER BY score ASC
            LIMIT ?
        ";

        // distance bind, score bind (same literal), then drug binds, then limit
        $params = array_merge([$literal, $literal], $drugs, [$topK]);

        return array_map(
            fn ($r) => (array) $r,
            DB::select($sql, $params)
        );
    }

    /** @return list<string> known generics mentioned in the question */
    private function mentionedDrugs(string $question): array
    {
        $known = DB::table('documents')
            ->whereNotNull('drug_generic')
            ->distinct()
            ->pluck('drug_generic')
            ->all();

        $q = mb_strtolower($question);

        return array_values(array_filter(
            $known,
            fn ($drug) => $drug && str_contains($q, mb_strtolower($drug))
        ));
    }
}
