<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_event_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('event_name')->unique();
            $table->json('schema');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('event_name');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_event_schemas');
    }
};
