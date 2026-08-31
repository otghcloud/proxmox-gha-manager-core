<?php

namespace Tests\Unit;

use App\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DisplayTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_timestamps_are_rendered_in_the_configured_timezone_without_moving_the_instant(): void
    {
        app(SettingsRepository::class)->set('timezone', 'Europe/London');

        $stored = Carbon::parse('2026-08-29 21:22:00', 'UTC');

        $this->assertSame('2026-08-29 22:22:00', $stored->forDisplay()->format('Y-m-d H:i:s'));
        $this->assertTrue($stored->equalTo($stored->forDisplay()));
    }

    public function test_the_process_timezone_is_never_changed_by_the_setting(): void
    {
        $before = date_default_timezone_get();

        app(SettingsRepository::class)->set('timezone', 'Australia/Sydney');
        app(SettingsRepository::class)->displayTimezone();

        $this->assertSame($before, date_default_timezone_get());
        $this->assertSame($before, now()->getTimezone()->getName());
    }

    public function test_a_setting_written_elsewhere_is_visible_without_a_fresh_instance(): void
    {
        $repository = app(SettingsRepository::class);
        $repository->set('timezone', 'Europe/London');

        $this->assertSame('Europe/London', $repository->displayTimezone());

        // A second process writing the same key must be picked up by the long-lived instance.
        (new SettingsRepository)->set('timezone', 'Australia/Sydney');

        $this->assertSame('Australia/Sydney', $repository->displayTimezone());
    }
}
