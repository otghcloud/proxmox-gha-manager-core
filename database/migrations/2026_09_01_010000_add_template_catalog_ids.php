<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runner_templates', function (Blueprint $table): void {
            $table->string('template_catalog_id')->nullable()->after('build_target')->index();
        });

        Schema::table('image_builds', function (Blueprint $table): void {
            $table->string('template_catalog_id')->nullable()->after('target')->index();
        });

        $path = rtrim((string) config('builds.image_builder_path'), '/').'/templates.json';
        $catalog = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($catalog) || ! is_array($catalog['templates'] ?? null)) {
            throw new RuntimeException('The installed template catalog is required to migrate template catalog IDs.');
        }

        foreach ($catalog['templates'] as $entry) {
            if (! is_array($entry) || ! is_string($entry['id'] ?? null) || ! is_string($entry['target'] ?? null)) {
                continue;
            }

            DB::table('runner_templates')->where('build_target', $entry['target'])->update(['template_catalog_id' => $entry['id']]);
            DB::table('image_builds')->where('target', $entry['target'])->update(['template_catalog_id' => $entry['id']]);
        }
    }

    public function down(): void
    {
        Schema::table('image_builds', function (Blueprint $table): void {
            $table->dropIndex(['template_catalog_id']);
            $table->dropColumn('template_catalog_id');
        });

        Schema::table('runner_templates', function (Blueprint $table): void {
            $table->dropIndex(['template_catalog_id']);
            $table->dropColumn('template_catalog_id');
        });
    }
};
