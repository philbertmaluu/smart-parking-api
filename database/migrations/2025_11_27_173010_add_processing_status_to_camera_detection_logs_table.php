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
        Schema::table('camera_detection_logs', function (Blueprint $table) {
            $table->string('processing_status', 50)->default('pending')->after('processed')->comment('Processing status: pending, pending_vehicle_type, processed, failed');
            $table->index('processing_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('camera_detection_logs', function (Blueprint $table) {
            $table->dropIndex(['processing_status']);
            $table->dropColumn('processing_status');
        });
    }
};
