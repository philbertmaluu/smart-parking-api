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
        Schema::create('vehicle_body_type_prices', function (Blueprint $table) {
            $table->id();
            $table->integer('body_type_id')->references('id')->on('vehicle_body_types');
            $table->integer('station_id')->references('id')->on('stations');
            $table->decimal('base_price', 8, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('body_type_id');
            $table->index('station_id');
            $table->index('effective_from');
            $table->index('effective_to');
            $table->index('is_active');

            // Unique constraint
            $table->unique(['body_type_id', 'station_id', 'effective_from'], 'vbp_body_type_station_effective_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_body_type_prices');
    }
};
