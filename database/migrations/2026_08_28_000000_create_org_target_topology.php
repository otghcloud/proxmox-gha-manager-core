<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxmox_targets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('proxmox_url');
            $table->string('proxmox_node');
            $table->string('proxmox_token_id');
            $table->text('proxmox_token_secret');
            $table->boolean('proxmox_verify_tls')->default(false);
            $table->string('proxmox_ca_bundle')->nullable();
            $table->string('proxmox_resource_pool')->nullable();
            $table->unsignedInteger('template_vmid_range_start')->default(801);
            $table->unsignedInteger('template_vmid_range_end')->default(899);
            $table->unsignedInteger('runner_vmid_range_start')->default(901);
            $table->unsignedInteger('runner_vmid_range_end')->default(999);
            $table->string('build_iso_storage')->nullable();
            $table->string('build_vm_storage')->nullable();
            $table->string('build_cpu_type')->default('host');
            $table->string('health_status')->default('healthy');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('current_vm_count')->default(0);
            $table->unsignedInteger('max_total_vms')->default(12);
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('runner_template_target', function (Blueprint $table) {
            $table->id();
            $table->foreignId('runner_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proxmox_target_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('template_vmid')->nullable();
            $table->string('build_iso_file')->nullable();
            $table->unsignedInteger('build_cores')->nullable();
            $table->unsignedInteger('build_memory_mb')->nullable();
            $table->unsignedInteger('build_disk_gb')->nullable();
            $table->string('availability_status')->default('unavailable');
            $table->timestamp('last_built_at')->nullable();
            $table->timestamps();

            $table->unique(['runner_template_id', 'proxmox_target_id']);
        });

        DB::statement('CREATE UNIQUE INDEX runner_template_target_vmid_unique ON runner_template_target (proxmox_target_id, template_vmid) WHERE template_vmid IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('runner_template_target');
        Schema::dropIfExists('proxmox_targets');
    }
};
