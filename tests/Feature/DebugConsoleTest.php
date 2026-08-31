<?php

namespace Tests\Feature;

use App\Enums\BuildStatus;
use App\Enums\RunnerState;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\ImageBuild;
use App\Models\ProxmoxTarget;
use App\Models\Runner;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DebugConsoleTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private ProxmoxTarget $target;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $account = GitHubAccount::create([
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'github_token' => 'ghp_example',
            'github_webhook_secret' => 'webhook-secret',
        ]);

        $this->environment = Environment::create([
            'name' => 'Production',
            'slug' => 'production',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'user@pve!token',
            'proxmox_token_secret' => 'proxmox-secret',
            'github_account_id' => $account->id,
        ]);

        $this->target = ProxmoxTarget::create([
            'name' => 'PVE 01',
            'slug' => 'pve-01',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!token',
            'proxmox_token_secret' => 'token-secret',
            'health_status' => 'healthy',
            'enabled' => true,
        ]);
    }

    public function test_debug_page_is_reachable(): void
    {
        $this->get(route('debug.index'))->assertOk()->assertSee('Debug');
    }

    public function test_export_config_downloads_zip_archive(): void
    {
        $response = $this->get(route('debug.export-config'));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');
    }

    public function test_toggles_are_persisted(): void
    {
        $settings = app(SettingsRepository::class);

        $this->assertTrue($settings->bool(SettingsRepository::REAPING_ENABLED));

        $this->put(route('debug.toggle'), [
            'key' => SettingsRepository::REAPING_ENABLED,
            'enabled' => 0,
        ])->assertRedirect();

        $this->assertFalse(app(SettingsRepository::class)->bool(SettingsRepository::REAPING_ENABLED));
    }

    public function test_reaping_is_skipped_while_disabled(): void
    {
        app(SettingsRepository::class)->set(SettingsRepository::REAPING_ENABLED, '0');

        $this->artisan('runners:reap')
            ->expectsOutputToContain('Reaping is disabled')
            ->assertExitCode(0);
    }

    public function test_reap_all_is_requested_for_the_next_scheduled_pass(): void
    {
        $this->post(route('debug.reap-all'))->assertRedirect();

        $this->assertTrue(app(SettingsRepository::class)->bool(SettingsRepository::FORCE_REAP_ALL_REQUESTED, false));
    }

    public function test_scheduled_force_reap_ignores_normal_reaping_rules_and_consumes_the_request(): void
    {
        $runner = $this->runner(901, RunnerState::Idle);
        $settings = app(SettingsRepository::class);
        $settings->set(SettingsRepository::REAPING_ENABLED, '0');
        $settings->set(SettingsRepository::FORCE_REAP_ALL_REQUESTED, '1');

        Http::fake([
            '*/qemu/901/status/current*' => Http::response(['data' => ['status' => 'stopped']]),
            '*/tasks/*' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
            '*' => Http::response(['data' => 'UPID:pve:0000:0000:0000:qmdelete:901:root@pam:']),
        ]);

        $this->artisan('runners:reap')
            ->expectsOutputToContain('Destroyed 1 VM(s) in total.')
            ->assertExitCode(0);

        $this->assertSame(RunnerState::Destroyed, $runner->fresh()->state);
        $this->assertFalse($settings->bool(SettingsRepository::FORCE_REAP_ALL_REQUESTED, false));
    }

    public function test_runner_history_is_cleared_without_touching_live_runners(): void
    {
        $live = $this->runner(901, RunnerState::Idle);
        $historic = $this->runner(902, RunnerState::Destroyed);
        $historic->events()->create(['to_state' => RunnerState::Destroyed->value, 'created_at' => now()]);

        $this->delete(route('debug.runner-history'))->assertRedirect();

        $this->assertModelExists($live);
        $this->assertModelMissing($historic);
        $this->assertSame(0, $historic->events()->count());
    }

    public function test_build_history_is_cleared_including_running_builds(): void
    {
        ImageBuild::create([
            'environment_id' => $this->environment->id,
            'triggered_by' => $this->user->id,
            'target' => 'pmx-ubuntu2404',
            'status' => BuildStatus::Running,
            'started_at' => now(),
        ]);

        $this->delete(route('debug.build-history'))->assertRedirect();

        $this->assertSame(0, ImageBuild::count());
    }

    private function runner(int $vmid, RunnerState $state): Runner
    {
        return Runner::create([
            'environment_id' => $this->environment->id,
            'proxmox_target_id' => $this->target->id,
            'vmid' => $vmid,
            'runner_name' => "gha-debug-{$vmid}",
            'state' => $state,
            'state_changed_at' => now(),
        ]);
    }
}
