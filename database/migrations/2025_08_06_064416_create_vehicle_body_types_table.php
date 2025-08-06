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
        Schema::create('vehicle_body_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('car, truck, bus, motorcycle, trailer');
            $table->text('description')->nullable();
            $table->string('category')->nullable()->comment('light, medium, heavy');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('name');
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_body_types');
    }
};
