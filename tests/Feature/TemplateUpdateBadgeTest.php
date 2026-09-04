<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\GitHubAccount;
use App\Models\RunnerTemplate;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateUpdateBadgeTest extends TestCase
{
    use RefreshDatabase;

    private string $catalogDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogDirectory = sys_get_temp_dir().'/template-badge-'.bin2hex(random_bytes(4));
        mkdir($this->catalogDirectory, 0755, true);
        config(['builds.image_builder_path' => $this->catalogDirectory]);

        file_put_contents($this->catalogDirectory.'/templates.json', json_encode(['templates' => [[
            'id' => 'ubuntu-24.04',
            'name' => 'Ubuntu 24.04',
            'description' => 'Ubuntu runner image',
            'metadata' => ['version' => '2026.09.03.1'],
            'platform' => ['type' => 'linux'],
            'builders' => ['packer' => [
                'buildable' => true,
                'disabled_reason' => null,
                'type' => 'packer',
                'path' => 'templates/ubuntu/ubuntu2404/packer',
                'build_manifest' => 'templates/ubuntu/ubuntu2404/packer/build.json',
                'provisioner' => ['runner_images_directory' => 'images/ubuntu', 'scripts_root_required' => true],
            ]],
        ]]], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        unlink($this->catalogDirectory.'/templates.json');
        rmdir($this->catalogDirectory);

        parent::tearDown();
    }

    private function template(): RunnerTemplate
    {
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

        return RunnerTemplate::create([
            'environment_id' => $environment->id,
            'name' => 'Ubuntu 24.04',
            'os' => 'linux',
            'template_catalog_id' => 'ubuntu-24.04',
        ]);
    }

    public function test_the_list_shows_an_update_badge_when_a_newer_version_is_published(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('installed_at', now()->toIso8601String());
        $settings->set(SettingsRepository::TEMPLATE_AUTO_CHECK_ENABLED, true);
        $settings->set(SettingsRepository::TEMPLATE_UPDATES_AVAILABLE, json_encode([
            'remote_versions' => ['ubuntu-24.04' => '2026.09.04.1'],
        ], JSON_THROW_ON_ERROR));

        $this->actingAs(User::factory()->create());
        $this->template();

        $this->getJson(route('templates.index').'?draw=1&start=0&length=10', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('2026.09.04.1 available', false);
    }

    public function test_the_list_shows_no_badge_when_the_installed_version_is_current(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('installed_at', now()->toIso8601String());
        $settings->set(SettingsRepository::TEMPLATE_AUTO_CHECK_ENABLED, true);
        $settings->set(SettingsRepository::TEMPLATE_UPDATES_AVAILABLE, json_encode([
            'remote_versions' => ['ubuntu-24.04' => '2026.09.03.1'],
        ], JSON_THROW_ON_ERROR));

        $this->actingAs(User::factory()->create());
        $this->template();

        $this->getJson(route('templates.index').'?draw=1&start=0&length=10', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertDontSee('available', false);
    }
}
