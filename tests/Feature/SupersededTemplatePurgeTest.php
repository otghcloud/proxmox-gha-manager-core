<?php

namespace Tests\Feature;

use App\Enums\RunnerState;
use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\Pool;
use App\Models\ProxmoxTarget;
use App\Models\RetiredTemplateVmid;
use App\Models\Runner;
use App\Models\RunnerTemplate;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupersededTemplatePurgeTest extends TestCase
{
    use RefreshDatabase;

    private RunnerTemplate $template;

    private ProxmoxTarget $target;

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
        $environment = Environment::create([
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
            'environment_id' => $environment->id,
            'name' => 'Ubuntu 24.04',
            'os' => 'linux',
            'template_catalog_id' => 'ubuntu-24.04',
        ]);
    }

    private function retired(int $vmid = 801): RetiredTemplateVmid
    {
        return RetiredTemplateVmid::create([
            'runner_template_id' => $this->template->id,
            'proxmox_target_id' => $this->target->id,
            'vmid' => $vmid,
            'generation' => 1,
            'retired_at' => now(),
        ]);
    }

    public function test_it_destroys_the_vm_and_marks_the_record_purged(): void
    {
        $retired = $this->retired();

        Http::fake([
            '*/status/current*' => Http::response(['data' => ['status' => 'stopped']]),
            '*/tasks/*' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
            '*' => Http::response(['data' => 'UPID:pve:0000:0000:0000:qmdestroy:801:root@pam:']),
        ]);

        $this->post(route('templates.superseded.purge', [$this->template, $retired]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($retired->fresh()->deleted_at);
    }

    public function test_it_refuses_to_purge_a_template_that_still_has_runners(): void
    {
        $retired = $this->retired(802);

        $pool = Pool::create([
            'environment_id' => $this->template->environment_id,
            'runner_template_id' => $this->template->id,
            'name' => 'ubuntu',
            'labels' => ['self-hosted'],
            'cores' => 2,
            'memory' => 2048,
            'boot_timeout_seconds' => 180,
        ]);

        Runner::create([
            'environment_id' => $this->template->environment_id,
            'pool_id' => $pool->id,
            'proxmox_target_id' => $this->target->id,
            'runner_name' => 'gha-ubuntu-901-abc',
            'vmid' => 901,
            'source_template_vmid' => 802,
            'state' => RunnerState::Idle->value,
            'state_changed_at' => now(),
        ]);

        $this->post(route('templates.superseded.purge', [$this->template, $retired]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($retired->fresh()->deleted_at);
    }
}
