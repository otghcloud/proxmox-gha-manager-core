<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->unsignedInteger('min_idle_runners')->default(0)->after('max_concurrent');
        });
    }

    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->dropColumn('min_idle_runners');
        });
    }
};
