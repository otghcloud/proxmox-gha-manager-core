<?php

namespace Tests\Feature;

use App\Enums\BuildStatus;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\ImageBuild;
use App\Models\LogEntry;
use App\Models\ProxmoxTarget;
use App\Models\RunnerTemplate;
use App\Models\User;
use App\Models\WorkflowJob;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogEntryStorageTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());

        $this->directory = sys_get_temp_dir().'/log-entries-'.bin2hex(random_bytes(4));
        mkdir($this->directory, 0755, true);

        $account = GitHubAccount::create([
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'github_token' => 'token',
            'github_webhook_secret' => 'secret',
        ]);
        $this->environment = Environment::create([
            'name' => 'Production',
            'slug' => 'production',
            'github_account_id' => $account->id,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->directory);

        parent::tearDown();
    }

    private function build(?string $logPath = null): ImageBuild
    {
        $target = ProxmoxTarget::firstOrCreate(['slug' => 'pve-01'], [
            'name' => 'PVE 01',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!runner',
            'proxmox_token_secret' => 'secret',
        ]);
        $template = RunnerTemplate::firstOrCreate([
            'environment_id' => $this->environment->id,
            'name' => 'Ubuntu 24.04',
        ], [
            'os' => 'linux',
            'template_catalog_id' => 'ubuntu-24.04',
        ]);

        return ImageBuild::create([
            'environment_id' => $this->environment->id,
            'runner_template_id' => $template->id,
            'proxmox_target_id' => $target->id,
            'template_catalog_id' => 'ubuntu-24.04',
            'status' => BuildStatus::Succeeded,
            'log_path' => $logPath,
        ]);
    }

    public function test_it_stores_one_row_per_log_and_replaces_on_repeat(): void
    {
        $build = $this->build();

        LogEntry::store($build, LogEntry::CHANNEL_BUILD, 'first');
        LogEntry::store($build, LogEntry::CHANNEL_BUILD, 'second attempt');

        $this->assertSame(1, $build->logEntries()->count());
        $this->assertSame('second attempt', $build->storedLog()->body);
        $this->assertSame(strlen('second attempt'), $build->storedLog()->byte_size);
    }

    public function test_the_build_page_falls_back_to_the_stored_log_when_the_file_is_gone(): void
    {
        $this->actingAs(User::factory()->create());

        $build = $this->build($this->directory.'/missing.log');
        LogEntry::store($build, LogEntry::CHANNEL_BUILD, 'stored build output');

        $this->get(route('builds.show', $build))
            ->assertOk()
            ->assertSee('stored build output');
    }

    public function test_the_job_log_endpoint_falls_back_to_the_stored_log(): void
    {
        $this->actingAs(User::factory()->create());

        $job = WorkflowJob::create([
            'environment_id' => $this->environment->id,
            'github_job_id' => 4242,
            'github_run_id' => 99,
            'repository_full_name' => 'otghcloud/demo',
            'job_name' => 'build',
            'workflow_name' => 'CI',
            'status' => 'completed',
            'log_path' => $this->directory.'/missing-job.log',
        ]);

        LogEntry::store($job, LogEntry::CHANNEL_JOB, 'stored job output');

        $this->get(route('jobs.log', $job))
            ->assertOk()
            ->assertSee('stored job output');
    }

    public function test_the_prune_command_only_deletes_files_already_stored(): void
    {
        $stored = $this->directory.'/stored.log';
        $unstored = $this->directory.'/unstored.log';
        file_put_contents($stored, 'stored');
        file_put_contents($unstored, 'not stored');

        $withEntry = $this->build($stored);
        LogEntry::store($withEntry, LogEntry::CHANNEL_BUILD, 'stored');

        $withoutEntry = $this->build($unstored);

        $this->artisan('logs:prune-files')->assertExitCode(0);

        $this->assertFileDoesNotExist($stored);
        $this->assertFileExists($unstored);
        $this->assertNull($withEntry->fresh()->log_path);
        $this->assertNotNull($withoutEntry->fresh()->log_path);
    }
}
