<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Query;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Ingestion: documents + chunks per drug
        $perDrug = DB::table('documents as d')
            ->leftJoin('chunks as c', 'c.document_id', '=', 'd.id')
            ->whereNotNull('d.drug_generic')
            ->groupBy('d.drug_generic')
            ->orderBy('d.drug_generic')
            ->get([
                'd.drug_generic',
                DB::raw('count(distinct d.id) as documents'),
                DB::raw('count(c.id) as chunks'),
            ]);

        // Query analytics
        $queryStats = [
            'total'       => Query::count(),
            'answered'    => Query::where('status', 'done')->count(),
            'failed'      => Query::where('status', 'failed')->count(),
            'avg_latency' => (int) round((float) Query::where('status', 'done')->avg('latency_ms')),
        ];

        $providerSplit = Query::select('chat_provider', DB::raw('count(*) as n'))
            ->groupBy('chat_provider')->pluck('n', 'chat_provider')->all();

        $recentQueries = Query::latest()->limit(10)
            ->get(['id', 'question', 'status', 'chat_provider', 'latency_ms', 'created_at']);

        return view('dashboard', [
            'totals' => [
                'documents' => Document::count(),
                'chunks'    => DB::table('chunks')->count(),
                'drugs'     => Document::whereNotNull('drug_generic')->distinct('drug_generic')->count('drug_generic'),
                'audits'    => AuditLog::count(),
            ],
            'perDrug'       => $perDrug,
            'queryStats'    => $queryStats,
            'providerSplit' => $providerSplit,
            'recentQueries' => $recentQueries,
        ]);
    }
}
