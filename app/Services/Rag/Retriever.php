<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\DB;

/**
 * Vector retrieval over pgvector using cosine distance (<=>), with two lightweight
 * hybrid signals layered on top of the pure embedding score:
 *
 *  - drug-entity boost: if the question names a known drug, prefer that drug's chunks;
 *  - field-intent boost: if the question asks about a specific label section
 *    (contraindications, warnings, interactions, ...), prefer chunks from that field.
 *
 * Together these fix the generic-query ranking issue seen in the PoC, where semantically
 * similar sections (e.g. adverse reactions) crowded out the section actually asked for.
 */
class Retriever
{
    private const DRUG_BOOST  = 0.15;
    private const FIELD_BOOST = 0.12;

    /** question keyword => openFDA field(s) it implies */
    private const FIELD_INTENTS = [
        'contraindication'      => ['contraindications'],
        'warning'               => ['warnings', 'warnings_and_cautions'],
        'caution'               => ['warnings_and_cautions'],
        'precaution'            => ['warnings', 'warnings_and_cautions'],
        'interaction'           => ['drug_interactions'],
        'adverse'               => ['adverse_reactions'],
        'side effect'           => ['adverse_reactions'],
        'dose'                  => ['dosage_and_administration'],
        'dosage'                => ['dosage_and_administration'],
        'administration'        => ['dosage_and_administration'],
        'indication'            => ['indications_and_usage'],
        'used for'              => ['indications_and_usage'],
        'used to treat'         => ['indications_and_usage'],
    ];

    public function __construct(private Embedder $embedder) {}

    /**
     * @return list<array{id:int,document_id:int,content:string,distance:float,score:float,drug_generic:?string,drug_brand:?string,field:?string,title:?string,url:?string}>
     */
    public function retrieve(string $question, ?int $topK = null): array
    {
        $topK    = $topK ?? (int) config('kardiorag.retrieval.top_k', 6);
        $literal = $this->embedder->embedToLiteral($question);
        $drugs   = $this->mentionedDrugs($question);
        $fields  = $this->intendedFields($question);

        // Build the boost expressions. Each matching condition lowers the score
        // (cosine distance), making those rows rank earlier.
        $drugBoost  = '0';
        if (! empty($drugs)) {
            $ph = implode(',', array_fill(0, count($drugs), '?'));
            $drugBoost = 'CASE WHEN d.drug_generic IN (' . $ph . ') THEN ' . self::DRUG_BOOST . ' ELSE 0 END';
        }

        $fieldBoost = '0';
        if (! empty($fields)) {
            $ph = implode(',', array_fill(0, count($fields), '?'));
            $fieldBoost = 'CASE WHEN d.field IN (' . $ph . ') THEN ' . self::FIELD_BOOST . ' ELSE 0 END';
        }

        $sql = "
            SELECT c.id, c.document_id, c.content,
                   (c.embedding <=> ?::vector) AS distance,
                   (c.embedding <=> ?::vector) - ($drugBoost) - ($fieldBoost) AS score,
                   d.drug_generic, d.drug_brand, d.field, d.title, d.url
            FROM chunks c
            JOIN documents d ON d.id = c.document_id
            WHERE c.embedding IS NOT NULL
            ORDER BY score ASC
            LIMIT ?
        ";

        // Bind order matches placeholder order in the SQL above:
        // distance literal, score literal, drug values, field values, limit.
        $params = array_merge([$literal, $literal], $drugs, $fields, [$topK]);

        return array_map(fn ($r) => (array) $r, DB::select($sql, $params));
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

    /** @return list<string> openFDA fields implied by the question */
    private function intendedFields(string $question): array
    {
        $q = mb_strtolower($question);
        $fields = [];
        foreach (self::FIELD_INTENTS as $keyword => $mapped) {
            if (str_contains($q, $keyword)) {
                $fields = array_merge($fields, $mapped);
            }
        }

        return array_values(array_unique($fields));
    }
}
