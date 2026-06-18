@extends('layout')
@section('title', 'Dashboard · KardioRAG')

@php
    $maxChunks = max(1, (int) ($perDrug->max('chunks') ?? 0));
@endphp

@section('content')
    <h1>Dashboard</h1>
    <p class="sub">Ingestion, retrieval knowledge base, and query analytics.</p>

    <section>
        <div class="grid">
            <div class="stat"><div class="v">{{ $totals['drugs'] }}</div><div class="k">Drugs ingested</div></div>
            <div class="stat"><div class="v">{{ $totals['documents'] }}</div><div class="k">Documents</div></div>
            <div class="stat"><div class="v">{{ $totals['chunks'] }}</div><div class="k">Embedded chunks</div></div>
            <div class="stat"><div class="v">{{ $totals['audits'] }}</div><div class="k">Audit events</div></div>
        </div>
    </section>

    <section class="card">
        <h2>Chunks per drug</h2>
        @forelse($perDrug as $row)
            <div class="bar-row">
                <span class="lbl">{{ $row->drug_generic }}</span>
                <span class="bar-track"><span class="bar-fill" style="width: {{ (int) round($row->chunks / $maxChunks * 100) }}%"></span></span>
                <span class="val">{{ $row->chunks }} ch</span>
            </div>
        @empty
            <p class="sub">No drugs ingested yet. Run <code>php artisan kardiorag:ingest</code>.</p>
        @endforelse
    </section>

    <section>
        <div class="grid" style="grid-template-columns: repeat(3, 1fr)">
            <div class="stat"><div class="v">{{ $queryStats['total'] }}</div><div class="k">Total queries</div></div>
            <div class="stat"><div class="v">{{ $queryStats['answered'] }}</div><div class="k">Answered ({{ $queryStats['failed'] }} failed)</div></div>
            <div class="stat"><div class="v">{{ $queryStats['avg_latency'] }} ms</div><div class="k">Avg answer latency</div></div>
        </div>
    </section>

    <section class="card">
        <h2>Provider usage</h2>
        @forelse($providerSplit as $provider => $n)
            <div class="bar-row">
                <span class="lbl">{{ $provider ?? 'n/a' }}</span>
                <span class="bar-track"><span class="bar-fill" style="width: {{ (int) round($n / max(1, $queryStats['total']) * 100) }}%"></span></span>
                <span class="val">{{ $n }}</span>
            </div>
        @empty
            <p class="sub">No queries yet.</p>
        @endforelse
    </section>

    <section class="card">
        <h2>Recent queries</h2>
        <table>
            <thead><tr><th>#</th><th>Question</th><th>Provider</th><th>Status</th><th>Latency</th><th>When</th></tr></thead>
            <tbody>
            @forelse($recentQueries as $q)
                <tr>
                    <td>{{ $q->id }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($q->question, 60) }}</td>
                    <td>{{ $q->chat_provider }}</td>
                    <td><span class="pill {{ $q->status }}">{{ $q->status }}</span></td>
                    <td>{{ $q->latency_ms ? $q->latency_ms.' ms' : '–' }}</td>
                    <td>{{ $q->created_at?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="sub">No queries yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
