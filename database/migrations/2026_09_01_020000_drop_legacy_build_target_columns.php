<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runner_templates', function (Blueprint $table): void {
            $table->dropColumn('build_target');
        });

        Schema::table('image_builds', function (Blueprint $table): void {
            $table->dropColumn('target');
        });
    }

    public function down(): void
    {
        Schema::table('image_builds', function (Blueprint $table): void {
            $table->string('target')->nullable()->after('runner_template_id');
        });

        Schema::table('runner_templates', function (Blueprint $table): void {
            $table->string('build_target')->nullable()->after('os');
        });
    }
};
