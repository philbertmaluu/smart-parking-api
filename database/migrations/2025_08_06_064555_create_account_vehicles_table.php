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
        Schema::create('account_vehicles', function (Blueprint $table) {
            $table->id();
            $table->integer('account_id')->references('id')->on('accounts');
            $table->integer('vehicle_id')->references('id')->on('vehicles');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('registered_at');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('account_id');
            $table->index('vehicle_id');
            $table->index('is_primary');

            // Unique constraint
            $table->unique(['account_id', 'vehicle_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_vehicles');
    }
};
