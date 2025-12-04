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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->foreignId('account_id')->nullable()->constrained('accounts');
            $table->foreignId('vehicle_passage_id')->nullable()->constrained('vehicle_passages');
            $table->foreignId('bundle_subscription_id')->nullable()->constrained('bundle_subscriptions');
            $table->enum('transaction_type', ['debit', 'credit', 'refund']);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_before', 10, 2)->nullable();
            $table->decimal('balance_after', 10, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('transaction_number');
            $table->index('account_id');
            $table->index('vehicle_passage_id');
            $table->index('bundle_subscription_id');
            $table->index('transaction_type');
            $table->index('processed_by');
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
