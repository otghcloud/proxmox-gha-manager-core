<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_builds', function (Blueprint $table): void {
            $table->unsignedInteger('process_pid')->nullable()->after('exit_code');
        });
    }

    public function down(): void
    {
        Schema::table('image_builds', function (Blueprint $table): void {
            $table->dropColumn('process_pid');
        });
    }
};
