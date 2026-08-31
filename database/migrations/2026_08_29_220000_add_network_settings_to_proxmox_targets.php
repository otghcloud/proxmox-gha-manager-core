<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxmox_targets', function (Blueprint $table): void {
            $table->string('network_bridge')->default('vmbr0')->after('build_cpu_type');
            $table->unsignedSmallInteger('vlan_tag')->nullable()->after('network_bridge');
        });
    }

    public function down(): void
    {
        Schema::table('proxmox_targets', function (Blueprint $table): void {
            $table->dropColumn(['network_bridge', 'vlan_tag']);
        });
    }
};
