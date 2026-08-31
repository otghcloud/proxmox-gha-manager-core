<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_builds', function (Blueprint $table): void {
            $table->unsignedInteger('template_vmid')->nullable()->after('target');
            $table->uuid('rebuild_batch_id')->nullable()->index()->after('template_vmid');
            $table->unsignedInteger('sequence')->default(0)->after('rebuild_batch_id');
        });

        Schema::table('runners', function (Blueprint $table): void {
            $table->unsignedInteger('source_template_vmid')->nullable()->index()->after('vmid');
        });

        Schema::table('runner_template_target', function (Blueprint $table): void {
            $table->unsignedInteger('generation')->default(0)->after('template_vmid');
        });

        Schema::create('retired_template_vmids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('runner_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proxmox_target_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('vmid');
            $table->unsignedInteger('generation')->default(0);
            $table->timestamp('retired_at');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['proxmox_target_id', 'deleted_at']);
        });

        // Runners already alive were cloned from the template currently recorded for their node.
        DB::table('runners')
            ->whereNull('source_template_vmid')
            ->whereNot('state', 'destroyed')
            ->orderBy('id')
            ->each(function (object $runner): void {
                $vmid = DB::table('runner_template_target')
                    ->join('pools', 'pools.runner_template_id', '=', 'runner_template_target.runner_template_id')
                    ->where('pools.id', $runner->pool_id)
                    ->where('runner_template_target.proxmox_target_id', $runner->proxmox_target_id)
                    ->value('runner_template_target.template_vmid');

                if ($vmid !== null) {
                    DB::table('runners')->where('id', $runner->id)->update(['source_template_vmid' => $vmid]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('retired_template_vmids');

        Schema::table('runner_template_target', function (Blueprint $table): void {
            $table->dropColumn('generation');
        });

        Schema::table('runners', function (Blueprint $table): void {
            $table->dropColumn('source_template_vmid');
        });

        Schema::table('image_builds', function (Blueprint $table): void {
            $table->dropColumn(['template_vmid', 'rebuild_batch_id', 'sequence']);
        });
    }
};
