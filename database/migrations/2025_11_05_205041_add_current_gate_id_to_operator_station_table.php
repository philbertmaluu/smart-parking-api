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
        Schema::table('operator_station', function (Blueprint $table) {
            $table->foreignId('current_gate_id')->nullable()->after('station_id')->constrained('gates')->onDelete('set null');
            $table->timestamp('gate_selected_at')->nullable()->after('current_gate_id');
            $table->index('current_gate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operator_station', function (Blueprint $table) {
            $table->dropForeign(['current_gate_id']);
            $table->dropIndex(['current_gate_id']);
            $table->dropColumn(['current_gate_id', 'gate_selected_at']);
        });
    }
};

