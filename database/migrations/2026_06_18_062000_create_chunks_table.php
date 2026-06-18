<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dim = (int) config('kardiorag.embed_dim', 768);

        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->integer('ord')->default(0);          // position within document
            $table->longText('content');
            $table->integer('char_count')->default(0);
            $table->string('embed_model')->nullable();
            $table->timestamps();

            $table->index('document_id');
        });

        // pgvector column + ANN index (raw SQL: not supported by the schema builder).
        DB::statement("ALTER TABLE chunks ADD COLUMN embedding vector($dim)");
        DB::statement('CREATE INDEX chunks_embedding_idx ON chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
