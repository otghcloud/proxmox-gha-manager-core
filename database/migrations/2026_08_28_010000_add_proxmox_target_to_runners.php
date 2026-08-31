<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runners', function (Blueprint $table) {
            $table->foreignId('proxmox_target_id')
                ->after('environment_id')
                ->constrained('proxmox_targets')
                ->restrictOnDelete();

            $table->index(['proxmox_target_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::table('runners', function (Blueprint $table) {
            $table->dropForeign(['proxmox_target_id']);
            $table->dropIndex(['proxmox_target_id', 'state']);
            $table->dropColumn('proxmox_target_id');
        });
    }
};
