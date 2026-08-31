<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_builds', function (Blueprint $table) {
            $table->foreignId('proxmox_target_id')->nullable()->constrained('proxmox_targets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('image_builds', function (Blueprint $table) {
            $table->dropForeign(['proxmox_target_id']);
            $table->dropColumn('proxmox_target_id');
        });

    }
};
