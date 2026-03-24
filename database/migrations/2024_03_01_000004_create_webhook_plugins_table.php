<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_plugins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('class');
            $table->json('config')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_plugins');
    }
};
