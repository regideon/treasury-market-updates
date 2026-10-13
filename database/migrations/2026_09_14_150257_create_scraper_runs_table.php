<?php

use App\Enums\ScraperRunStatus;
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
        Schema::create('scraper_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status')->default(ScraperRunStatus::Pending->value);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('records_upserted')->default(0);
            $table->text('error')->nullable();
            $table->string('raw_snapshot_path')->nullable();
            $table->timestamps();

            $table->index(['data_source_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraper_runs');
    }
};
