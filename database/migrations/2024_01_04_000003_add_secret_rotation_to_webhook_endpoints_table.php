<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->string('previous_secret')->nullable()->after('secret');
            $table->timestamp('secret_rotated_at')->nullable()->after('previous_secret');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->dropColumn(['previous_secret', 'secret_rotated_at']);
        });
    }
};
