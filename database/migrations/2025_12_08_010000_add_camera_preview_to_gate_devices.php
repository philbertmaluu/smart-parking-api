<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gate_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('gate_devices', 'last_preview_path')) {
                $table->string('last_preview_path')->nullable()->after('last_ping_at');
            }

            if (!Schema::hasColumn('gate_devices', 'last_preview_at')) {
                $table->timestamp('last_preview_at')->nullable()->after('last_preview_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gate_devices', function (Blueprint $table) {
            if (Schema::hasColumn('gate_devices', 'last_preview_path')) {
                $table->dropColumn('last_preview_path');
            }

            if (Schema::hasColumn('gate_devices', 'last_preview_at')) {
                $table->dropColumn('last_preview_at');
            }
        });
    }
};
