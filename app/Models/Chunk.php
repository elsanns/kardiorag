<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chunk extends Model
{
    protected $fillable = [
        'document_id', 'ord', 'content', 'char_count', 'embed_model',
    ];

    // `embedding` is a pgvector column handled with raw SQL; keep it out of mass assignment.
    protected $guarded = ['embedding'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
