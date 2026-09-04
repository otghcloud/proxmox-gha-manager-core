<?php

namespace Tests\Feature;

use App\Enums\BuildStatus;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\ImageBuild;
use App\Models\ProxmoxTarget;
use App\Models\RunnerTemplate;
use App\Models\User;
use App\Services\Builds\BuildCanceller;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BuildForceKillTest extends TestCase
{
    use RefreshDatabase;

    private RunnerTemplate $template;

    private ProxmoxTarget $target;

    private Environment $environment;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());
        $this->actingAs(User::factory()->create());

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
        $this->target = ProxmoxTarget::create([
            'name' => 'PVE 01',
            'slug' => 'pve-01',
            'proxmox_url' => 'https://pve.example.com:8006/api2/json',
            'proxmox_node' => 'pve',
            'proxmox_token_id' => 'root@pam!runner',
            'proxmox_token_secret' => 'secret',
        ]);
        $this->template = RunnerTemplate::create([
            'environment_id' => $this->environment->id,
            'name' => 'Ubuntu 24.04',
            'os' => 'linux',
            'template_catalog_id' => 'ubuntu-24.04',
        ]);
    }

    private function build(BuildStatus $status, ?int $pid = null): ImageBuild
    {
        return ImageBuild::create([
            'environment_id' => $this->environment->id,
            'runner_template_id' => $this->template->id,
            'proxmox_target_id' => $this->target->id,
            'template_catalog_id' => 'ubuntu-24.04',
            'status' => $status,
            'process_pid' => $pid,
        ]);
    }

    public function test_it_force_kills_a_running_build(): void
    {
        $build = $this->build(BuildStatus::Running);

        $this->post(route('builds.cancel', $build))
            ->assertRedirect()
            ->assertSessionHas('success');

        $build->refresh();

        $this->assertSame(BuildStatus::Cancelled, $build->status);
        $this->assertNotNull($build->finished_at);
        $this->assertNull($build->process_pid);
    }

    public function test_it_refuses_to_kill_a_finished_build(): void
    {
        $build = $this->build(BuildStatus::Succeeded);

        $this->post(route('builds.cancel', $build))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(BuildStatus::Succeeded, $build->fresh()->status);
    }

    public function test_it_terminates_the_tracked_process(): void
    {
        $sleeper = new Process(['sleep', '60']);
        $sleeper->start();
        $pid = $sleeper->getPid();

        $build = $this->build(BuildStatus::Running, $pid);

        $this->assertTrue($sleeper->isRunning());

        app(BuildCanceller::class)->cancel($build->refresh());

        $deadline = microtime(true) + 10;
        while ($sleeper->isRunning() && microtime(true) < $deadline) {
            usleep(100000);
        }

        $this->assertFalse($sleeper->isRunning(), 'The tracked process should have been terminated.');
        $this->assertSame(BuildStatus::Cancelled, $build->fresh()->status);
    }
}
