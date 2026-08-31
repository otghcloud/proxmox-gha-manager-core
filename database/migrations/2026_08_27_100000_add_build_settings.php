<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runner_templates', function (Blueprint $table) {
            // Null means the template was created outside this application and cannot be rebuilt here.
            $table->string('build_target')->nullable()->after('os');
        });
    }

    public function down(): void
    {
        Schema::table('runner_templates', function (Blueprint $table) {
            $table->dropColumn('build_target');
        });
    }
};
