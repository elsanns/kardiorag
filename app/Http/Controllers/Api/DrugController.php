<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Read endpoint making imports observable: ingested drugs with document/chunk counts.
 */
class DrugController extends Controller
{
    public function index(): JsonResponse
    {
        $drugs = DB::table('documents as d')
            ->leftJoin('chunks as c', 'c.document_id', '=', 'd.id')
            ->whereNotNull('d.drug_generic')
            ->groupBy('d.drug_generic')
            ->orderBy('d.drug_generic')
            ->get([
                'd.drug_generic',
                DB::raw('count(distinct d.id) as documents'),
                DB::raw('count(c.id) as chunks'),
                DB::raw('max(d.updated_at) as last_ingested_at'),
            ]);

        return response()->json([
            'data' => $drugs->map(fn ($r) => [
                'drug'             => $r->drug_generic,
                'documents'        => (int) $r->documents,
                'chunks'           => (int) $r->chunks,
                'last_ingested_at' => $r->last_ingested_at,
            ]),
            'meta' => [
                'total'      => $drugs->count(),
                'disclaimer' => config('kardiorag.openfda.disclaimer'),
            ],
        ]);
    }
}
