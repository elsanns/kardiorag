<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('openfda');     // data source
            $table->string('source_id')->nullable();           // e.g. openFDA set id
            $table->string('drug_generic')->nullable();
            $table->string('drug_brand')->nullable();
            $table->string('field')->nullable();               // warnings, contraindications, ...
            $table->string('title')->nullable();
            $table->text('url')->nullable();                   // citation link
            $table->longText('content');                       // normalized source text
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['source', 'drug_generic']);
            $table->unique(['source', 'source_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
