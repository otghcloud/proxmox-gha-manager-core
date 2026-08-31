<?php

use App\Services\Templates\TemplateUpdateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runner_template_target', function (Blueprint $table): void {
            $table->string('version')->nullable()->after('generation');
        });

        Schema::table('image_builds', function (Blueprint $table): void {
            $table->string('version')->nullable()->after('template_vmid');
        });

        // Backfill existing pivot rows if local templates.json is available
        $pivots = DB::table('runner_template_target')
            ->join('runner_templates', 'runner_templates.id', '=', 'runner_template_target.runner_template_id')
            ->select('runner_template_target.id', 'runner_templates.build_target')
            ->get();

        foreach ($pivots as $pivot) {
            if ($pivot->build_target) {
                $version = TemplateUpdateService::getLocalVersionForTarget($pivot->build_target);
                if ($version) {
                    DB::table('runner_template_target')->where('id', $pivot->id)->update(['version' => $version]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('runner_template_target', function (Blueprint $table): void {
            $table->dropColumn('version');
        });

        Schema::table('image_builds', function (Blueprint $table): void {
            $table->dropColumn('version');
        });
    }
};
