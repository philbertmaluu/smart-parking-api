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
        // Add missing indexes to vehicle_passages table for faster queries
        Schema::table('vehicle_passages', function (Blueprint $table) {
            // Index for active passage lookup (most used query)
            if (!$this->indexExists('vehicle_passages', 'idx_vehicle_id_exit_time_status')) {
                $table->index(['vehicle_id', 'exit_time', 'status'], 'idx_vehicle_id_exit_time_status');
            }

            // Index for plate number lookups
            if (!$this->indexExists('vehicle_passages', 'idx_vehicle_id_entry_time')) {
                $table->index(['vehicle_id', 'entry_time'], 'idx_vehicle_id_entry_time');
            }

            // Index for gate-based queries
            if (!$this->indexExists('vehicle_passages', 'idx_entry_gate_id_exit_time')) {
                $table->index(['entry_gate_id', 'exit_time'], 'idx_entry_gate_id_exit_time');
            }

            // Index for exit gate queries
            if (!$this->indexExists('vehicle_passages', 'idx_exit_gate_id')) {
                $table->index('exit_gate_id', 'idx_exit_gate_id');
            }

            // Index for status queries
            if (!$this->indexExists('vehicle_passages', 'idx_status')) {
                $table->index('status', 'idx_status');
            }

            // Index for operator queries
            if (!$this->indexExists('vehicle_passages', 'idx_entry_operator_id')) {
                $table->index('entry_operator_id', 'idx_entry_operator_id');
            }

            if (!$this->indexExists('vehicle_passages', 'idx_exit_operator_id')) {
                $table->index('exit_operator_id', 'idx_exit_operator_id');
            }
        });

        // Add missing indexes to vehicles table
        Schema::table('vehicles', function (Blueprint $table) {
            // Index for plate number lookups
            if (!$this->indexExists('vehicles', 'idx_plate_number')) {
                $table->index('plate_number', 'idx_plate_number');
            }
        });

        // Add missing indexes to accounts table
        Schema::table('accounts', function (Blueprint $table) {
            if (!$this->indexExists('accounts', 'idx_account_number')) {
                $table->index('account_number', 'idx_account_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_passages', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_vehicle_id_exit_time_status');
            $table->dropIndexIfExists('idx_vehicle_id_entry_time');
            $table->dropIndexIfExists('idx_entry_gate_id_exit_time');
            $table->dropIndexIfExists('idx_exit_gate_id');
            $table->dropIndexIfExists('idx_status');
            $table->dropIndexIfExists('idx_entry_operator_id');
            $table->dropIndexIfExists('idx_exit_operator_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_plate_number');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_account_number');
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        return in_array($index, array_column(
            DB::select("SHOW INDEX FROM {$table}"),
            'Key_name'
        ));
    }
};
