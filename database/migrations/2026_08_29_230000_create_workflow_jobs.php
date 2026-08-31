<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('runner_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('github_job_id');
            $table->unsignedBigInteger('github_run_id')->nullable();
            $table->unsignedInteger('run_attempt')->nullable();
            $table->string('repository_full_name');
            $table->string('workflow_name')->nullable();
            $table->string('job_name');
            $table->string('runner_name')->nullable();
            $table->string('head_branch')->nullable();
            $table->string('head_sha')->nullable();
            $table->json('labels')->nullable();
            $table->string('status')->index();
            $table->string('conclusion')->nullable()->index();
            $table->string('html_url')->nullable();
            $table->json('steps')->nullable();
            $table->string('log_path')->nullable();
            $table->timestamp('log_fetched_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['environment_id', 'github_job_id']);
            $table->index(['environment_id', 'created_at']);
        });

        Schema::table('runners', function (Blueprint $table): void {
            $table->string('spawn_reason')->default('warm')->after('pool_id');
        });

        // Runners created for a specific job already carry its id; everything else was pre-spawned.
        DB::table('runners')->whereNotNull('workflow_job_id')->update(['spawn_reason' => 'job']);
    }

    public function down(): void
    {
        Schema::table('runners', function (Blueprint $table): void {
            $table->dropColumn('spawn_reason');
        });

        Schema::dropIfExists('workflow_jobs');
    }
};
