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
        Schema::create('vehicle_passages', function (Blueprint $table) {
            $table->id();
            $table->string('passage_number')->unique()->comment('Auto-generated receipt/reference number');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('account_id')->nullable()->constrained('accounts')->comment('null for cash customers');
            $table->foreignId('bundle_subscription_id')->nullable()->constrained('bundle_subscriptions')->comment('null for non-bundle');
            $table->foreignId('payment_type_id')->constrained('payment_types');

            // Entry details
            $table->timestamp('entry_time');
            $table->foreignId('entry_operator_id')->constrained('users');
            $table->foreignId('entry_gate_id')->constrained('gates');
            $table->foreignId('entry_station_id')->constrained('stations');

            // Exit details
            $table->timestamp('exit_time')->nullable();
            $table->foreignId('exit_operator_id')->nullable()->constrained('users');
            $table->foreignId('exit_gate_id')->nullable()->constrained('gates');
            $table->foreignId('exit_station_id')->nullable()->constrained('stations');

            // Pricing and payment
            $table->decimal('base_amount', 8, 2);
            $table->decimal('discount_amount', 8, 2)->default(0.00);
            $table->decimal('total_amount', 8, 2);

            // Status and flags
            $table->enum('passage_type', ['toll', 'free', 'exempted'])->default('toll');
            $table->boolean('is_exempted')->default(false);
            $table->string('exemption_reason')->nullable();
            $table->enum('status', ['active', 'cancelled', 'refunded'])->default('active');

            // Additional tracking
            $table->integer('duration_minutes')->nullable()->comment('Calculated field for exit - entry time');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('passage_number');
            $table->index('vehicle_id');
            $table->index('account_id');
            $table->index('bundle_subscription_id');
            $table->index('payment_type_id');
            $table->index('entry_time');
            $table->index('exit_time');
            $table->index('entry_station_id');
            $table->index('exit_station_id');
            $table->index('passage_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_passages');
    }
};
