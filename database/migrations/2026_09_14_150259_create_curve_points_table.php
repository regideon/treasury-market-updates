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
        Schema::create('curve_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_indicator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scraper_run_id')->nullable()->constrained()->nullOnDelete();
            $table->date('as_of_date');
            $table->string('point_key');
            $table->string('point_label')->nullable();
            $table->decimal('value', 18, 6)->nullable();
            $table->decimal('bid', 18, 6)->nullable();
            $table->decimal('offer', 18, 6)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['market_indicator_id', 'as_of_date', 'point_key'], 'curve_points_indicator_date_point_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curve_points');
    }
};
