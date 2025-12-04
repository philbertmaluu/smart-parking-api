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
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->foreignId('station_id')->nullable()->constrained('stations');
            $table->integer('total_passages')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0.00);
            $table->decimal('cash_payments', 10, 2)->default(0.00);
            $table->decimal('card_payments', 10, 2)->default(0.00);
            $table->decimal('account_payments', 10, 2)->default(0.00);
            $table->integer('bundle_passages')->default(0);
            $table->integer('exempted_passages')->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('report_date');
            $table->index('station_id');
            $table->index('generated_by');
            $table->index('generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
