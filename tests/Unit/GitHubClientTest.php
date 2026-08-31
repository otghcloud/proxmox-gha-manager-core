<?php

namespace Tests\Unit;

use App\Models\GitHubAccount;
use App\Services\GitHub\GitHubClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_account_uses_user_runner_endpoints(): void
    {
        $account = GitHubAccount::create([
            'account_type' => 'user',
            'login' => 'lee-brooks',
            'github_token' => 'token',
            'github_webhook_secret' => 'secret',
            'github_api_url' => 'https://api.github.com',
        ]);

        Http::fake([
            'https://api.github.com/user/actions/runners*' => Http::response([
                'runners' => [['id' => 42, 'name' => 'personal-runner', 'status' => 'online', 'busy' => false]],
            ]),
        ]);

        $runners = (new GitHubClient($account))->listRunners();

        $this->assertArrayHasKey('personal-runner', $runners);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.github.com/user/actions/runners?per_page=100&page=1');
    }

    public function test_personal_account_generates_and_deletes_jit_runners(): void
    {
        $account = GitHubAccount::create([
            'account_type' => 'user',
            'login' => 'lee-brooks',
            'github_token' => 'token',
            'github_webhook_secret' => 'secret',
            'github_api_url' => 'https://api.github.com',
        ]);

        Http::fake([
            'https://api.github.com/user/actions/runners/generate-jitconfig' => Http::response([
                'encoded_jit_config' => 'encoded',
                'runner' => ['id' => 42],
            ]),
            'https://api.github.com/user/actions/runners/42' => Http::response([], 204),
        ]);

        $client = new GitHubClient($account);
        $jit = $client->generateJitConfig('personal-runner', ['self-hosted', 'linux']);
        $client->deleteRunner($jit->runnerId);

        $this->assertSame(42, $jit->runnerId);
        $this->assertSame('encoded', $jit->encodedJitConfig);
        Http::assertSentCount(2);
    }
}
