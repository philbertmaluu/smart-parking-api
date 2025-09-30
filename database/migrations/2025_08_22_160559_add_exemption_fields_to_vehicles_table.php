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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('is_exempted')->default(false)->after('is_registered');
            $table->string('exemption_reason')->nullable()->after('is_exempted');
            $table->timestamp('exemption_expires_at')->nullable()->after('exemption_reason');

            // Indexes
            $table->index('is_exempted');
            $table->index('exemption_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['is_exempted']);
            $table->dropIndex(['exemption_expires_at']);
            $table->dropColumn(['is_exempted', 'exemption_reason', 'exemption_expires_at']);
        });
    }
};
