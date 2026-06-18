<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'source', 'source_id', 'drug_generic', 'drug_brand',
        'field', 'title', 'url', 'content', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }
}
