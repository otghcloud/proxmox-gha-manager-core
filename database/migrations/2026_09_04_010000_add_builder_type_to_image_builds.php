<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_builds', function (Blueprint $table): void {
            $table->string('builder_type')->nullable()->after('template_catalog_id');
            $table->index('builder_type');
        });
    }

    public function down(): void
    {
        Schema::table('image_builds', function (Blueprint $table): void {
            $table->dropIndex(['builder_type']);
            $table->dropColumn('builder_type');
        });
    }
};
