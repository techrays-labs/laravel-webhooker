<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->text('dead_letter_reason')->nullable()->after('idempotency_key');
            $table->timestamp('dead_lettered_at')->nullable()->after('dead_letter_reason');
            $table->index(['status', 'dead_lettered_at']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropIndex(['status', 'dead_lettered_at']);
            $table->dropColumn(['dead_letter_reason', 'dead_lettered_at']);
        });
    }
};
