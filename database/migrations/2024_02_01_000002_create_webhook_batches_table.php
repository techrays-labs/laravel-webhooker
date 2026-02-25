<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->unique();
            $table->string('event_name')->nullable();
            $table->unsignedInteger('total_events')->default(0);
            $table->unsignedInteger('successful_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->string('status')->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_batches');
    }
};
