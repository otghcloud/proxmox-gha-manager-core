<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxmox_targets', function (Blueprint $table) {
            $table->timestamp('drained_at')->nullable()->after('last_health_check_at');
        });
    }

    public function down(): void
    {
        Schema::table('proxmox_targets', function (Blueprint $table) {
            $table->dropColumn('drained_at');
        });
    }
};
