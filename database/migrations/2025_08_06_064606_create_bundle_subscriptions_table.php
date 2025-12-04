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
        Schema::create('bundle_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_number')->unique()->comment('Auto-generated');
            $table->integer('account_id')->references('id')->on('accounts');
            $table->integer('bundle_id')->references('id')->on('bundles');
            $table->timestamp('start_datetime')->nullable();
            $table->timestamp('end_datetime')->nullable();
            $table->decimal('amount', 10, 2);
            $table->integer('passages_used')->default(0);
            $table->enum('status', ['pending', 'active', 'suspended', 'expired', 'cancelled'])->default('active');
            $table->boolean('auto_renew')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('subscription_number');
            $table->index('account_id');
            $table->index('bundle_id');
            $table->index('start_datetime');
            $table->index('end_datetime');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_subscriptions');
    }
};
