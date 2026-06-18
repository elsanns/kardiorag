<?php

return [

    // Active providers. "ollama" = local/on-prem, "claude" = hosted (Anthropic).
    'chat_provider'  => env('LLM_PROVIDER', 'ollama'),
    'embed_provider' => env('EMBED_PROVIDER', 'ollama'),

    // Embedding vector dimension (must match the embedding model + DB column).
    'embed_dim' => (int) env('EMBED_DIM', 768),

    // Retrieval
    'retrieval' => [
        'top_k' => 6,
    ],

    // Chunking (characters; simple + deterministic for the demo)
    'chunk' => [
        'size'    => 900,
        'overlap' => 150,
    ],

    'ollama' => [
        'base_url'    => rtrim(env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'), '/'),
        'chat_model'  => env('OLLAMA_CHAT_MODEL', 'llama3.2:3b'),
        'embed_model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
        'timeout'     => 600,
    ],

    'claude' => [
        'api_key'    => env('ANTHROPIC_API_KEY', ''),
        'chat_model' => env('ANTHROPIC_CHAT_MODEL', 'claude-opus-4-8'),
        'base_url'   => 'https://api.anthropic.com',
        'version'    => '2023-06-01',
        'timeout'    => 120,
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
