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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('vehicle_passage_id')->constrained('vehicle_passages');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->comment('For invoice payments');
            $table->decimal('amount', 8, 2);
            $table->string('payment_method');
            $table->foreignId('issued_by')->constrained('users');
            $table->timestamp('issued_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('receipt_number');
            $table->index('vehicle_passage_id');
            $table->index('invoice_id');
            $table->index('issued_by');
            $table->index('issued_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
