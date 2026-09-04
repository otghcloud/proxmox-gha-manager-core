<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_entries', function (Blueprint $table): void {
            $table->id();
            $table->morphs('loggable');
            $table->string('channel')->index();
            $table->longText('body');
            $table->unsignedBigInteger('byte_size')->default(0);
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['loggable_type', 'loggable_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_entries');
    }
};
