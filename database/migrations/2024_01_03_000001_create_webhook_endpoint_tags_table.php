<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoint_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('endpoint_id');
            $table->string('tag');
            $table->timestamp('created_at')->nullable();

            $table->foreign('endpoint_id')
                ->references('id')
                ->on('webhook_endpoints')
                ->onDelete('cascade');

            $table->index('tag');
            $table->unique(['endpoint_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoint_tags');
    }
};
