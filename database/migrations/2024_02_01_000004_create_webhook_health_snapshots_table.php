<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->float('success_rate');
            $table->float('average_response_time_ms');
            $table->unsignedInteger('total_events');
            $table->unsignedInteger('failed_events');
            $table->string('status');
            $table->timestamp('recorded_at');
            $table->index(['endpoint_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_health_snapshots');
    }
};
