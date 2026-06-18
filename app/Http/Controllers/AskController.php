<?php

namespace App\Http\Controllers;

use App\Jobs\AnswerQuestionJob;
use App\Models\Query;
use App\Services\Rag\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AskController extends Controller
{
    /** Ask page with a few recent questions. */
    public function index(): View
    {
        $recent = Query::latest()->limit(8)->get(['id', 'question', 'status', 'created_at']);

        return view('ask', [
            'recent'   => $recent,
            'provider' => config('kardiorag.chat_provider'),
        ]);
    }

    /** Accept a question, queue generation, return the query id to poll. */
    public function submit(Request $request, RagService $rag): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $query = $rag->startQuery(trim($data['question']));
        AnswerQuestionJob::dispatch($query->id);

        return response()->json([
            'query_id' => $query->id,
            'status'   => $query->status,
            'poll_url' => route('ask.status', $query),
        ], 202);
    }

    /** Poll a query's status/result. */
    public function status(Query $query): JsonResponse
    {
        return response()->json([
            'query_id'   => $query->id,
            'status'     => $query->status,
            'question'   => $query->question,
            'answer'     => $query->answer,
            'sources'    => $query->sources ?? [],
            'provider'   => $query->chat_provider,
            'latency_ms' => $query->latency_ms,
            'error'      => $query->error,
        ]);
    }
}
