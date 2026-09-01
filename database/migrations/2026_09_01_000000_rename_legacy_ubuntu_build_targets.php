<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Align persisted values with the target keys published by the template catalog.
     */
    public function up(): void
    {
        $targets = [
            'pmx-ubuntu2404' => 'ubuntu-24.04',
            'pmx-ubuntu2604' => 'ubuntu-26.04',
            'pmx-ubuntu-slim' => 'ubuntu-slim',
        ];

        foreach ($targets as $legacy => $catalogTarget) {
            DB::table('runner_templates')->where('build_target', $legacy)->update(['build_target' => $catalogTarget]);
            DB::table('image_builds')->where('target', $legacy)->update(['target' => $catalogTarget]);
        }
    }

    public function down(): void
    {
        $targets = [
            'ubuntu-24.04' => 'pmx-ubuntu2404',
            'ubuntu-26.04' => 'pmx-ubuntu2604',
            'ubuntu-slim' => 'pmx-ubuntu-slim',
        ];

        foreach ($targets as $catalogTarget => $legacy) {
            DB::table('runner_templates')->where('build_target', $catalogTarget)->update(['build_target' => $legacy]);
            DB::table('image_builds')->where('target', $catalogTarget)->update(['target' => $legacy]);
        }
    }
};
