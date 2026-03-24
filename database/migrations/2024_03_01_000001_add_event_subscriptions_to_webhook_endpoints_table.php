<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->text('event_filters')->nullable()->after('rate_limit_per_minute');
            $table->json('transform_config')->nullable()->after('event_filters');
            $table->string('transformer_class')->nullable()->after('transform_config');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->dropColumn(['event_filters', 'transform_config', 'transformer_class']);
        });
    }
};
