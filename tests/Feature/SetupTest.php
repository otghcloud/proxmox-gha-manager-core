<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unconfigured_install_is_redirected_to_setup(): void
    {
        $this->get('/')->assertRedirect(route('setup.show'));
        $this->get('/login')->assertRedirect(route('setup.show'));
    }

    public function test_the_setup_wizard_creates_an_administrator_and_marks_the_install_complete(): void
    {
        $response = $this->post('/setup', [
            'app_url' => 'https://runners.example.com',
            'timezone' => 'Europe/London',
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $response->assertRedirect(route('github-accounts.create'));

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $this->assertTrue(app(SettingsRepository::class)->isInstalled());
        $this->assertAuthenticated();
    }

    public function test_setup_is_unreachable_once_installed(): void
    {
        app(SettingsRepository::class)->set('installed_at', now()->toIso8601String());

        $this->get('/setup')->assertRedirect(route('dashboard'));
    }

    public function test_importing_configuration_zip_restores_backup(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'test_zip_').'.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('.env', 'APP_NAME="Imported Test"');
        $zip->close();

        $file = new \Illuminate\Http\UploadedFile($zipPath, 'backup.zip', 'application/zip', null, true);

        $response = $this->post(route('setup.import'), [
            'config_file' => $file,
        ]);

        $response->assertRedirect(route('login'));

        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }
    }

    public function test_the_health_endpoint_is_always_reachable(): void
    {
        $this->get('/healthz')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_the_saved_timezone_does_not_change_the_storage_timezone(): void
    {
        $originalTimezone = date_default_timezone_get();
        $originalConfig = config('app.timezone');

        app(SettingsRepository::class)->set('timezone', 'Europe/London');

        (new AppServiceProvider($this->app))->boot();

        // Long-running workers boot once, so a runtime timezone switch here would silently write
        // timestamps in a different zone to the web process.
        $this->assertSame($originalConfig, config('app.timezone'));
        $this->assertSame($originalTimezone, date_default_timezone_get());
    }
}
