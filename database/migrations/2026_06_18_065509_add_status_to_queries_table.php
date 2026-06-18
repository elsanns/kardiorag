<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            // Async lifecycle: pending -> processing -> done | failed
            $table->string('status')->default('pending')->after('question');
            $table->json('sources')->nullable()->after('answer');
            $table->text('error')->nullable()->after('sources');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'sources', 'error']);
        });
    }
};
