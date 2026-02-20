<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->string('route_token')->nullable()->after('id');
            $table->unique('route_token');
        });

        // Backfill existing endpoints with generated route tokens
        DB::table('webhook_endpoints')
            ->whereNull('route_token')
            ->get()
            ->each(function ($endpoint) {
                DB::table('webhook_endpoints')
                    ->where('id', $endpoint->id)
                    ->update(['route_token' => 'ep_'.Str::random(12)]);
            });
    }

    public function down(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->dropColumn('route_token');
        });
    }
};
