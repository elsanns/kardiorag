<?php

namespace App\Services\Rag;

use App\Models\Chunk;
use App\Services\Llm\ProviderFactory;
use Illuminate\Support\Facades\DB;

/**
 * Generates embeddings via the active (local) embed provider and writes them into the
 * pgvector column using raw SQL (the column is not managed by Eloquent).
 */
class Embedder
{
    public function __construct(private ProviderFactory $providers) {}

    /** Format a float vector as a pgvector literal, e.g. "[0.1,0.2,...]". */
    public static function toLiteral(array $vector): string
    {
        return '[' . implode(',', array_map(static fn ($v) => (float) $v, $vector)) . ']';
    }

    /** Embed a single text and return the pgvector literal for a SQL bind. */
    public function embedToLiteral(string $text): string
    {
        $provider = $this->providers->embed();
        $vector = $provider->embed([$text])[0];

        return self::toLiteral($vector);
    }

    /**
     * Embed each chunk's content and persist the vector.
     *
     * @param  iterable<Chunk>  $chunks
     */
    public function embedChunks(iterable $chunks): int
    {
        $provider = $this->providers->embed();
        $model    = config('kardiorag.ollama.embed_model');
        $count    = 0;

        foreach ($chunks as $chunk) {
            $vector  = $provider->embed([$chunk->content])[0];
            $literal = self::toLiteral($vector);

            DB::update(
                'UPDATE chunks SET embedding = ?::vector, embed_model = ?, updated_at = now() WHERE id = ?',
                [$literal, $model, $chunk->id]
            );
            $count++;
        }

        return $count;
    }
}
