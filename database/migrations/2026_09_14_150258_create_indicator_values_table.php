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
        Schema::create('indicator_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_indicator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scraper_run_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('value', 18, 6)->nullable();
            $table->string('value_text')->nullable();
            $table->date('as_of_date');
            $table->time('as_of_time')->nullable();
            $table->string('period_label')->nullable();
            $table->string('source_note')->nullable();
            $table->timestamps();

            $table->unique(['market_indicator_id', 'as_of_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_values');
    }
};
