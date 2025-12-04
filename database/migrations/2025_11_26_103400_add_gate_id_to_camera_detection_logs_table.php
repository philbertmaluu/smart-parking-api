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
            $table->unsignedBigInteger('gate_id')->nullable()->after('id');
            $table->foreign('gate_id')->references('id')->on('gates')->onDelete('set null');
            $table->index('gate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('camera_detection_logs', function (Blueprint $table) {
            $table->dropForeign(['gate_id']);
            $table->dropIndex(['gate_id']);
            $table->dropColumn('gate_id');
        });
    }
};
