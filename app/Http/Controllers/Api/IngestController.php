<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\IngestDrugJob;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public (no-auth) import endpoint. Triggers openFDA ingestion of a drug.
 *
 * Because there is no authentication in the demo, the input is constrained to the curated
 * cardiology drug set (so it can't be used to hammer openFDA with arbitrary queries) and the
 * route is rate-limited. Ingestion runs as a queued job; poll GET /api/v1/drugs for results.
 */
class IngestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $allowed = config('kardiorag.openfda.default_drugs', []);

        $data = $request->validate([
            'drug' => ['required', 'string', 'max:64'],
        ]);

        $drug = Str::lower(trim($data['drug']));

        if (! in_array($drug, $allowed, true)) {
            return response()->json([
                'error' => [
                    'code'    => 'drug_not_allowed',
                    'message' => 'Ingestion is restricted to the curated cardiology drug set in this demo.',
                    'allowed' => array_values($allowed),
                ],
            ], 422);
        }

        IngestDrugJob::dispatch($drug);

        AuditLog::record('api.ingest', [
            'resource_type' => 'drug',
            'resource_id'   => $drug,
        ]);

        return response()->json([
            'data' => [
                'drug'   => $drug,
                'status' => 'queued',
            ],
            'meta' => [
                'poll'       => route('api.drugs.index'),
                'disclaimer' => config('kardiorag.openfda.disclaimer'),
            ],
        ], 202);
    }
}
