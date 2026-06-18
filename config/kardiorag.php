<?php

return [

    /*
     | Chat tier selection (two-tier, fail-loud).
     | mode=local always uses on-prem Ollama (the safe default). mode=cloud is an
     | explicit opt-in that routes to one configurable cloud provider; if its key is
     | missing the provider throws rather than silently downgrading.
     */
    'chat' => [
        'mode'           => env('LLM_MODE', 'local'),         // local | cloud
        'cloud_provider' => env('CLOUD_PROVIDER', 'openai'),  // openai | gemini | anthropic
    ],

    // Embeddings stay local only (keeps the vector(768) column stable; no cloud embeddings).
    'embed_provider' => env('EMBED_PROVIDER', 'ollama'),

    // Embedding vector dimension (must match the embedding model + DB column).
    'embed_dim' => (int) env('EMBED_DIM', 768),

    // Retrieval
    'retrieval' => [
        'top_k' => 6,
    ],

    // Abuse/cost guards. Per-IP throttling is on the routes; this is a global daily
    // ceiling on generated answers (protects the model backend + cloud spend).
    'limits' => [
        'daily_queries' => (int) env('LLM_DAILY_CAP', 200),
    ],

    // Chunking (characters; simple + deterministic for the demo)
    'chunk' => [
        'size'    => 900,
        'overlap' => 150,
    ],

    // Local, on-prem provider (chat + embeddings).
    'ollama' => [
        'base_url'    => rtrim(env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'), '/'),
        'chat_model'  => env('OLLAMA_CHAT_MODEL', 'llama3.2:3b'),
        'embed_model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
        'timeout'     => 600,
    ],

    // Cloud chat providers (opt-in; model name configurable per provider).
    'providers' => [
        'openai' => [
            'api_key'    => env('OPENAI_API_KEY', ''),
            'chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-5'),
            'base_url'   => rtrim(env('OPENAI_BASE_URL', 'https://api.openai.com'), '/'),
            'timeout'    => 120,
        ],
        'gemini' => [
            'api_key'    => env('GEMINI_API_KEY', ''),
            'chat_model' => env('GEMINI_CHAT_MODEL', 'gemini-3-flash-preview'),
            'base_url'   => rtrim(env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'), '/'),
            'timeout'    => 120,
        ],
        'anthropic' => [
            'api_key'    => env('ANTHROPIC_API_KEY', ''),
            'chat_model' => env('ANTHROPIC_CHAT_MODEL', 'claude-opus-4-8'),
            'base_url'   => 'https://api.anthropic.com',
            'version'    => '2023-06-01',
            'timeout'    => 120,
        ],
    ],

    'openfda' => [
        'base_url' => rtrim(env('OPENFDA_BASE_URL', 'https://api.fda.gov'), '/'),
        'api_key'  => env('OPENFDA_API_KEY', ''),
        // Default curated set of cardiology-relevant generics for ingestion.
        'default_drugs' => [
            'amiodarone', 'metoprolol', 'warfarin', 'atorvastatin',
            'lisinopril', 'apixaban', 'digoxin', 'furosemide',
        ],
    ],
];
