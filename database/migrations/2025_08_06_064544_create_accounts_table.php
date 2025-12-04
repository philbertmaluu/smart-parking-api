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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('account_number')->unique()->comment('Auto-generated');
            $table->string('name');
            $table->enum('account_type', ['prepaid', 'postpaid'])->default('prepaid');
            $table->decimal('balance', 10, 2)->default(0.00);
            $table->decimal('credit_limit', 10, 2)->default(0.00)->comment('For postpaid accounts');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('customer_id');
            $table->index('account_number');
            $table->index('account_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
