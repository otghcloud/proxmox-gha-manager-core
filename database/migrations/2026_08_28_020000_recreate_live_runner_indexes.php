<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS runners_env_vmid_live_unique');
        DB::statement('DROP INDEX IF EXISTS runners_env_job_live_unique');
        DB::statement("CREATE UNIQUE INDEX runners_env_vmid_live_unique ON runners (environment_id, vmid) WHERE state <> 'destroyed'");
        DB::statement("CREATE UNIQUE INDEX runners_env_job_live_unique ON runners (environment_id, workflow_job_id) WHERE state <> 'destroyed' AND workflow_job_id IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS runners_env_vmid_live_unique');
        DB::statement('DROP INDEX IF EXISTS runners_env_job_live_unique');
        DB::statement("CREATE UNIQUE INDEX runners_env_vmid_live_unique ON runners (environment_id, vmid) WHERE state <> 'destroyed'");
        DB::statement("CREATE UNIQUE INDEX runners_env_job_live_unique ON runners (environment_id, workflow_job_id) WHERE state <> 'destroyed' AND workflow_job_id IS NOT NULL");
    }
};
