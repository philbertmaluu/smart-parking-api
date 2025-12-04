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
        Schema::create('user_has_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('permission_id')->constrained('permissions');
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Primary key
            $table->primary(['user_id', 'permission_id']);

            // Indexes
            $table->index('assigned_by');
            $table->index('assigned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_has_permissions');
    }
};
