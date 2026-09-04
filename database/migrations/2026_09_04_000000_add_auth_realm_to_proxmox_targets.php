<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxmox_targets', function (Blueprint $table) {
            // 'api_token' (existing PVEAPIToken flow) or 'password' (ticket auth, needed for
            // non-`root@pam` accounts calling endpoints Proxmox restricts to standard logins).
            $table->string('proxmox_auth_realm')->default('api_token')->after('proxmox_node');
            $table->string('proxmox_username')->nullable()->after('proxmox_auth_realm');
            $table->text('proxmox_password')->nullable()->after('proxmox_username');
        });
    }

    public function down(): void
    {
        Schema::table('proxmox_targets', function (Blueprint $table) {
            $table->dropColumn(['proxmox_auth_realm', 'proxmox_username', 'proxmox_password']);
        });
    }
};
