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
        Schema::create('bundle_vehicles', function (Blueprint $table) {
            $table->id();
            $table->integer('bundle_id')->references('id')->on('bundles');
            $table->integer('vehicle_body_type_id')->references('id')->on('vehicle_body_types');
            $table->integer('max_count')->default(1)->comment('How many of this vehicle type allowed');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('bundle_id');
            $table->index('vehicle_body_type_id');

            // Unique constraint
            $table->unique(['bundle_id', 'vehicle_body_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_vehicles');
    }
};
