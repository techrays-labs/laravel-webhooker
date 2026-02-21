<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->unsignedInteger('max_retries')->nullable()->after('timeout_seconds');
            $table->string('retry_strategy')->nullable()->after('max_retries');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->dropColumn(['max_retries', 'retry_strategy']);
        });
    }
};
