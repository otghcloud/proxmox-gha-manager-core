<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('github_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('webhook_id')->unique();
            $table->string('account_type');
            $table->string('login');
            $table->text('github_token');
            $table->text('github_webhook_secret');
            $table->string('github_api_url')->default('https://api.github.com');
            $table->unsignedInteger('github_runner_group_id')->default(1);
            $table->string('github_work_folder')->default('_work');
            $table->string('linux_ssh_username')->default('runner');
            $table->text('linux_ssh_password')->nullable();
            $table->string('windows_username')->nullable();
            $table->text('windows_password')->nullable();
            $table->timestamps();

            $table->unique(['account_type', 'login']);
        });

        Schema::create('environments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('github_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('enabled')->default(true);

            $table->unsignedInteger('max_lifetime_seconds')->default(21600);
            $table->unsignedInteger('idle_timeout_seconds')->default(900);
            $table->unsignedInteger('job_claim_timeout_seconds')->default(45);
            $table->boolean('keep_failed_vms')->default(false);

            $table->timestamps();
        });

        Schema::create('runner_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('os')->default('linux');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['environment_id', 'name']);
        });

        Schema::create('pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('runner_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->json('labels');
            $table->unsignedInteger('cores')->default(4);
            $table->unsignedInteger('memory')->default(8192);
            $table->unsignedInteger('max_concurrent')->default(4);
            $table->unsignedInteger('boot_timeout_seconds')->default(300);
            $table->string('runner_dir')->nullable();
            $table->timestamps();

            $table->unique(['environment_id', 'name']);
        });

        Schema::create('runners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pool_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('vmid');
            $table->string('runner_name')->unique();
            $table->unsignedBigInteger('github_runner_id')->nullable();
            $table->unsignedBigInteger('workflow_job_id')->nullable();
            $table->string('repository_full_name')->nullable();
            $table->string('state')->index();
            $table->string('ip_address')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('state_changed_at');
            $table->timestamp('destroyed_at')->nullable();
            $table->timestamps();

            $table->index(['environment_id', 'state']);
            $table->index(['pool_id', 'state']);
        });

        // Only live rows may hold a VMID or a job. Destroyed rows are retained for history,
        // so both uniqueness guarantees have to be partial rather than table-wide.
        DB::statement("CREATE UNIQUE INDEX runners_env_vmid_live_unique ON runners (environment_id, vmid) WHERE state <> 'destroyed'");
        DB::statement("CREATE UNIQUE INDEX runners_env_job_live_unique ON runners (environment_id, workflow_job_id) WHERE state <> 'destroyed' AND workflow_job_id IS NOT NULL");

        Schema::create('runner_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('runner_id')->constrained()->cascadeOnDelete();
            $table->string('from_state')->nullable();
            $table->string('to_state')->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['runner_id', 'created_at']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('github_account_id')->constrained()->cascadeOnDelete();
            $table->string('github_delivery_id')->nullable()->unique();
            $table->string('event')->nullable();
            $table->string('action')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->string('result')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at');

            $table->index(['github_account_id', 'created_at']);
        });

        Schema::create('image_builds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('runner_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target');
            $table->string('status')->index();
            $table->integer('exit_code')->nullable();
            $table->string('log_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_builds');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('runner_events');
        Schema::dropIfExists('runners');
        Schema::dropIfExists('pools');
        Schema::dropIfExists('runner_templates');
        Schema::dropIfExists('environments');
        Schema::dropIfExists('github_accounts');
        Schema::dropIfExists('settings');
    }
};
