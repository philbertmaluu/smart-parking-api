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
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->integer('bundle_type_id')->references('id')->on('bundle_types');
            $table->string('name');
            $table->decimal('amount', 10, 2);
            $table->integer('max_vehicles')->default(1);
            $table->integer('max_passages')->nullable()->comment('Limit number of passages (optional)');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('bundle_type_id');
            $table->index('amount');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundles');
    }
};
