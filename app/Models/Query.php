<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Query extends Model
{
    protected $table = 'queries';

    protected $fillable = [
        'user_id', 'question', 'answer', 'chat_provider', 'embed_provider',
        'retrieved_chunk_ids', 'latency_ms', 'prompt_tokens', 'completion_tokens',
    ];

    protected $casts = [
        'retrieved_chunk_ids' => 'array',
    ];
}
