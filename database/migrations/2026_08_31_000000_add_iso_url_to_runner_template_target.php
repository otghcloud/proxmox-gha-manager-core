<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runner_template_target', function (Blueprint $table) {
            $table->text('build_iso_url')->nullable()->after('build_iso_file');
        });
    }

    public function down(): void
    {
        Schema::table('runner_template_target', function (Blueprint $table) {
            $table->dropColumn('build_iso_url');
        });
    }
};
