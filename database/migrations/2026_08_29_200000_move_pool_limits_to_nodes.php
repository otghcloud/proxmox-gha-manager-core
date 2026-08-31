<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillNodeRows();

        Schema::table('pool_proxmox_target', function (Blueprint $table): void {
            $table->unsignedInteger('min_idle_runners')->default(0)->nullable(false)->change();
            $table->unsignedInteger('max_concurrent')->default(1)->nullable(false)->change();
        });

        Schema::table('pools', function (Blueprint $table): void {
            $table->dropColumn(['min_idle_runners', 'max_concurrent']);
        });
    }

    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table): void {
            $table->unsignedInteger('max_concurrent')->default(1);
            $table->unsignedInteger('min_idle_runners')->default(0);
        });

        // The pool-wide value can only be an approximation once limits are per node.
        foreach (DB::table('pools')->pluck('id') as $poolId) {
            $row = DB::table('pool_proxmox_target')
                ->where('pool_id', $poolId)
                ->selectRaw('max(min_idle_runners) as min_idle, max(max_concurrent) as max_concurrent')
                ->first();

            DB::table('pools')->where('id', $poolId)->update([
                'min_idle_runners' => (int) ($row->min_idle ?? 0),
                'max_concurrent' => max(1, (int) ($row->max_concurrent ?? 1)),
            ]);
        }

        Schema::table('pool_proxmox_target', function (Blueprint $table): void {
            $table->unsignedInteger('min_idle_runners')->nullable()->change();
            $table->unsignedInteger('max_concurrent')->nullable()->change();
        });
    }

    /**
     * Give every pool an explicit row per node, seeded from the pool-wide values it replaces.
     */
    private function backfillNodeRows(): void
    {
        $pools = DB::table('pools')->get(['id', 'runner_template_id', 'min_idle_runners', 'max_concurrent']);

        foreach ($pools as $pool) {
            $poolMinIdle = (int) ($pool->min_idle_runners ?? 0);
            $poolMaxConcurrent = max(1, (int) ($pool->max_concurrent ?? 1));

            DB::table('pool_proxmox_target')
                ->where('pool_id', $pool->id)
                ->whereNull('min_idle_runners')
                ->update(['min_idle_runners' => $poolMinIdle]);

            DB::table('pool_proxmox_target')
                ->where('pool_id', $pool->id)
                ->whereNull('max_concurrent')
                ->update(['max_concurrent' => $poolMaxConcurrent]);

            $existing = DB::table('pool_proxmox_target')
                ->where('pool_id', $pool->id)
                ->pluck('proxmox_target_id')
                ->all();

            $mapped = DB::table('runner_template_target')
                ->where('runner_template_id', $pool->runner_template_id)
                ->pluck('proxmox_target_id')
                ->all();

            $missing = array_diff($mapped, $existing);

            if ($missing === []) {
                continue;
            }

            DB::table('pool_proxmox_target')->insert(array_map(fn (int $targetId): array => [
                'pool_id' => $pool->id,
                'proxmox_target_id' => $targetId,
                'min_idle_runners' => $poolMinIdle,
                'max_concurrent' => $poolMaxConcurrent,
                'created_at' => now(),
                'updated_at' => now(),
            ], array_values($missing)));
        }
    }
};
