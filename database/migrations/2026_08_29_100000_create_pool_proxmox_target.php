<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pool_proxmox_target', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proxmox_target_id')->constrained()->cascadeOnDelete();
            // Null means "inherit the pool-wide value".
            $table->unsignedInteger('min_idle_runners')->nullable();
            $table->unsignedInteger('max_concurrent')->nullable();
            $table->timestamps();

            $table->unique(['pool_id', 'proxmox_target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pool_proxmox_target');
    }
};
