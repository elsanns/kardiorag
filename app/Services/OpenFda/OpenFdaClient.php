<?php

namespace App\Services\OpenFda;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client over the public openFDA drug-label API (no key required).
 * https://open.fda.gov/apis/drug/label/
 */
class OpenFdaClient
{
    /** openFDA label fields we ingest, mapped to human titles. */
    public const FIELDS = [
        'indications_and_usage' => 'Indications and usage',
        'contraindications'     => 'Contraindications',
        'warnings'              => 'Warnings',
        'warnings_and_cautions' => 'Warnings and cautions',
        'adverse_reactions'     => 'Adverse reactions',
        'drug_interactions'     => 'Drug interactions',
        'dosage_and_administration' => 'Dosage and administration',
    ];

    public function __construct(private array $config) {}

    /**
     * Fetch the most relevant label for a generic drug name and return normalized records.
     *
     * @return list<array{source_id:string,drug_generic:string,drug_brand:?string,field:string,title:string,content:string,url:?string}>
     */
    public function fetchDrugRecords(string $generic): array
    {
        $params = [
            'search' => 'openfda.generic_name:' . $generic,
            'limit'  => 1,
        ];
        if (! empty($this->config['api_key'])) {
            $params['api_key'] = $this->config['api_key'];
        }

        $res = Http::timeout(30)->acceptJson()
            ->get($this->config['base_url'] . '/drug/label.json', $params);

        if ($res->status() === 404) {
            return []; // openFDA returns 404 when no results match
        }
        if (! $res->successful()) {
            throw new RuntimeException("openFDA request failed for [$generic]: " . $res->body());
        }

        $result = $res->json('results.0');
        if (! is_array($result)) {
            return [];
        }

        $openfda  = $result['openfda'] ?? [];
        $setId    = $result['set_id'] ?? ($openfda['spl_set_id'][0] ?? $generic);
        $brand    = $openfda['brand_name'][0] ?? null;
        $url      = $setId ? "https://dailymed.nlm.nih.gov/dailymed/drugInfo.cfm?setid={$setId}" : null;

        $records = [];
        foreach (self::FIELDS as $field => $title) {
            if (empty($result[$field][0])) {
                continue;
            }
            $records[] = [
                'source_id'    => $setId,
                'drug_generic' => $generic,
                'drug_brand'   => $brand,
                'field'        => $field,
                'title'        => $title,
                'content'      => $this->clean($result[$field][0]),
                'url'          => $url,
            ];
        }

        return $records;
    }

    private function clean(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
