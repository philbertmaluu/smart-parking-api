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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('body_type_id')->constrained('vehicle_body_types');
            $table->string('plate_number')->unique();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->string('color')->nullable();
            $table->string('owner_name')->nullable();
            $table->boolean('is_registered')->default(false)->comment('For frequent users');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('body_type_id');
            $table->index('plate_number');
            $table->index('make');
            $table->index('model');
            $table->index('is_registered');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
