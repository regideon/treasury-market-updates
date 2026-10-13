<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            // Plain-language explanation of what this source actually is
            // (a live file, a bulk archive, a real API, ...) - shown as
            // part of the "Method" tooltip on the Market Indicators admin
            // page, so it's not just a URL with no context.
            $table->text('description')->nullable()->after('url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
