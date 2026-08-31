<?php

namespace Tests\Feature;

use App\Models\GitHubAccount;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GitHubAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());
        $this->actingAs(User::factory()->create());
    }

    public function test_account_create_page_hides_webhook_uuid(): void
    {
        $this->get(route('github-accounts.create'))
            ->assertOk()
            ->assertDontSee('Webhook UUID')
            ->assertDontSee('name="webhook_id"', false);
    }

    public function test_account_edit_page_exposes_webhook_uuid(): void
    {
        $account = $this->account();

        $this->get(route('github-accounts.edit', $account))
            ->assertOk()
            ->assertSee($account->webhook_id)
            ->assertSee('name="webhook_id"', false);
    }

    public function test_account_webhook_uuid_can_be_edited(): void
    {
        $account = $this->account();
        $webhookId = '11111111-2222-4333-8444-555555555555';

        $this->put(route('github-accounts.update', $account), [
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'webhook_id' => $webhookId,
            'github_token' => '',
            'github_webhook_secret' => '',
            'github_api_url' => 'https://api.github.com',
            'github_runner_group_id' => 1,
            'github_work_folder' => '_work',
            'linux_ssh_username' => 'runner',
            'linux_ssh_password' => '',
            'windows_username' => '',
            'windows_password' => '',
        ])->assertRedirect(route('github-accounts.index'));

        $this->assertSame($webhookId, $account->fresh()->webhook_id);
    }

    private function account(): GitHubAccount
    {
        return GitHubAccount::create([
            'account_type' => 'organization',
            'login' => 'otghcloud',
            'github_token' => 'token',
            'github_webhook_secret' => 'secret',
            'linux_ssh_username' => 'runner',
        ]);
    }
}
