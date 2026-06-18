@extends('layout')
@section('title', 'Ask · KardioRAG')

@section('content')
    <h1>Ask about cardiac drugs</h1>
    <p class="sub">Grounded answers from openFDA drug labels, with citations. Active model provider:
        <strong>{{ $provider }}</strong> (configurable, on-prem by default).</p>

    <div class="card">
        <form class="ask" id="ask-form" autocomplete="off">
            <input type="text" id="question" name="question"
                   placeholder="e.g. What are the contraindications for amiodarone?"
                   minlength="5" maxlength="500" required>
            <button type="submit" id="ask-btn">Ask</button>
        </form>
        <div class="examples" style="margin-top:12px">
            <a href="#" data-q="What are the key contraindications for amiodarone?">Amiodarone contraindications</a>
            <a href="#" data-q="What are the warnings for warfarin?">Warfarin warnings</a>
            <a href="#" data-q="What drug interactions does metoprolol have?">Metoprolol interactions</a>
        </div>
        <div class="status" id="status">
            <span class="spinner"></span>
            <span id="status-text">Working… the local model can take a minute or two on CPU.</span>
        </div>
    </div>

    <div class="card" id="result" style="display:none">
        <div class="answer" id="answer"></div>
        <ul class="sources" id="sources"></ul>
        <div class="answer-meta" id="meta"></div>
    </div>

    @if($recent->isNotEmpty())
        <div class="recent">
            <strong>Recent questions:</strong>
            @foreach($recent as $q)
                · <a href="#" data-q="{{ $q->question }}">{{ \Illuminate\Support\Str::limit($q->question, 48) }}</a>
            @endforeach
        </div>
    @endif

    <script nonce="{{ $cspNonce ?? '' }}">
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const form = document.getElementById('ask-form');
    const input = document.getElementById('question');
    const btn = document.getElementById('ask-btn');
    const statusBox = document.getElementById('status');
    const statusText = document.getElementById('status-text');
    const result = document.getElementById('result');
    const answerEl = document.getElementById('answer');
    const sourcesEl = document.getElementById('sources');
    const metaEl = document.getElementById('meta');

    // Only allow http(s) links (blocks javascript:/data: schemes in citation URLs).
    function safeHttpUrl(u) {
        try { const x = new URL(u); return (x.protocol === 'https:' || x.protocol === 'http:') ? x.href : null; }
        catch (_) { return null; }
    }

    document.querySelectorAll('[data-q]').forEach(a =>
        a.addEventListener('click', e => { e.preventDefault(); input.value = a.dataset.q; form.requestSubmit(); }));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const question = input.value.trim();
        if (question.length < 5) return;

        btn.disabled = true;
        result.style.display = 'none';
        sourcesEl.innerHTML = '';
        answerEl.classList.remove('error');
        statusBox.classList.add('show');
        statusText.textContent = 'Queued… the local model can take a minute or two on CPU.';

        try {
            const res = await fetch('{{ route('ask.submit') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ question }),
            });
            if (res.status === 429) { fail('Rate limit reached — please wait a moment and try again.'); return; }
            if (!res.ok) { fail('Could not submit the question.'); return; }
            const { poll_url } = await res.json();
            poll(poll_url);
        } catch (_) { fail('Network error submitting the question.'); }
    });

    async function poll(url) {
        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data.status === 'done') { render(data); }
            else if (data.status === 'failed') { fail(data.error || 'Generation failed.'); }
            else { statusText.textContent = 'Generating answer… (' + data.status + ')'; setTimeout(() => poll(url), 2000); }
        } catch (_) { setTimeout(() => poll(url), 2500); }
    }

    function render(data) {
        statusBox.classList.remove('show');
        btn.disabled = false;
        result.style.display = 'block';
        answerEl.classList.remove('error');
        answerEl.textContent = data.answer || '';
        sourcesEl.replaceChildren();
        (data.sources || []).forEach(s => {
            // Build with DOM nodes + textContent (never innerHTML) so source/model text can't inject markup.
            const li = document.createElement('li');
            const n = document.createElement('span'); n.className = 'n'; n.textContent = `[${s.n}]`;
            const name = document.createElement('strong'); name.textContent = s.drug_brand || s.drug_generic || '';
            const meta = document.createElement('div'); meta.className = 'meta';
            meta.textContent = `${s.drug_generic ?? ''} · distance ${s.distance}`;
            const url = safeHttpUrl(s.url);
            if (url) {
                const a = document.createElement('a');
                a.href = url; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.textContent = 'source';
                meta.append(' · ', a);
            }
            li.append(n, ' ', name, ' — ', document.createTextNode(s.title ?? ''), meta);
            sourcesEl.appendChild(li);
        });
        metaEl.textContent = `provider: ${data.provider} · ${data.latency_ms ?? '–'} ms · query #${data.query_id}`;
    }

    function fail(msg) {
        statusBox.classList.remove('show');
        btn.disabled = false;
        result.style.display = 'block';
        answerEl.classList.add('error');
        answerEl.textContent = msg;
        metaEl.textContent = '';
    }
    </script>
@endsection
