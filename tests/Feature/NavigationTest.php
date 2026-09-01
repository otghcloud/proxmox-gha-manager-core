<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());
        $this->actingAs(User::factory()->create());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function groupedRoutes(): array
    {
        return [
            'environments' => ['environments.index', '/config/environments'],
            'github accounts' => ['github-accounts.index', '/config/github-accounts'],
            'nodes' => ['nodes.index', '/config/nodes'],
            'builds' => ['builds.index', '/images/builds'],
            'pools' => ['pools.index', '/images/pools'],
            'templates' => ['templates.index', '/images/templates'],
            'jobs' => ['jobs.index', '/workflows/jobs'],
            'runners' => ['runners.index', '/workflows/runners'],
            'settings' => ['settings.overview', '/settings/overview'],
            'debug' => ['settings.debug.index', '/settings/debug'],
        ];
    }

    #[DataProvider('groupedRoutes')]
    public function test_each_section_lives_under_its_navigation_group(string $name, string $path): void
    {
        $this->assertSame($path, route($name, absolute: false));
        $this->get($path)->assertOk();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function legacyRoutes(): array
    {
        return [
            'environments' => ['/environments', '/config/environments'],
            'github accounts' => ['/github-accounts', '/config/github-accounts'],
            'targets' => ['/targets', '/config/nodes'],
            'builds' => ['/builds', '/images/builds'],
            'pools' => ['/pools', '/images/pools'],
            'templates' => ['/templates', '/images/templates'],
            'jobs' => ['/jobs', '/workflows/jobs'],
            'runners' => ['/runners', '/workflows/runners'],
        ];
    }

    #[DataProvider('legacyRoutes')]
    public function test_the_flat_urls_still_redirect(string $old, string $new): void
    {
        $this->get($old)->assertRedirect($new);
    }

    public function test_the_navigation_renders_every_group(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Config')
            ->assertSee('Images')
            ->assertSee('Workflows')
            ->assertSee('Settings')
            ->assertSee('dropdown-toggle', escape: false);
    }
}
