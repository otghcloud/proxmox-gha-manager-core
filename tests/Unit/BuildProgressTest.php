<?php

namespace Tests\Unit;

use App\Enums\BuildStatus;
use App\Models\ImageBuild;
use App\Services\Builds\BuildProgress;
use Tests\TestCase;

class BuildProgressTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/build-progress-'.bin2hex(random_bytes(4));
        mkdir($this->directory, 0755, true);

        config(['builds.image_builder_path' => $this->directory]);

        file_put_contents($this->directory.'/templates.json', json_encode([
            'templates' => [[
                'target' => 'pmx-ubuntu2404',
                'name' => 'Ubuntu 24.04 Proxmox',
                'build_requirements' => ['estimated_minutes' => 75],
                'build_stages' => [
                    ['id' => 'prepare', 'name' => 'Prepare', 'marker' => '[image-builder:stage:prepare]'],
                    ['id' => 'install-tools', 'name' => 'Install tools', 'marker' => '[image-builder:stage:install-tools]'],
                    ['id' => 'cleanup', 'name' => 'Cleanup', 'marker' => '[image-builder:stage:cleanup]'],
                ],
            ]],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);

        parent::tearDown();
    }

    public function test_running_build_progress_tracks_seen_stage_markers(): void
    {
        $log = $this->directory.'/build.log';
        file_put_contents($log, "first\n[image-builder:stage:prepare] Prepare\n[image-builder:stage:install-tools] Install tools\n");

        $progress = (new BuildProgress)->forBuild(new ImageBuild([
            'target' => 'pmx-ubuntu2404',
            'status' => BuildStatus::Running,
            'log_path' => $log,
        ]));

        $this->assertTrue($progress['available']);
        $this->assertSame(3, $progress['stage_count']);
        $this->assertSame(75, $progress['estimated_minutes']);
        $this->assertSame('1 hour 15 minutes', $progress['estimated_duration']);
        $this->assertSame(2, $progress['completed_count']);
        $this->assertSame(66, $progress['percent']);
        $this->assertSame('Install tools', $progress['current_stage']['name']);
        $this->assertSame(['complete', 'current', 'pending'], array_column($progress['stages'], 'state'));
    }

    public function test_succeeded_build_marks_every_stage_complete(): void
    {
        $log = $this->directory.'/build.log';
        file_put_contents($log, "[image-builder:stage:prepare] Prepare\n");

        $progress = (new BuildProgress)->forBuild(new ImageBuild([
            'target' => 'pmx-ubuntu2404',
            'status' => BuildStatus::Succeeded,
            'log_path' => $log,
        ]));

        $this->assertSame(100, $progress['percent']);
        $this->assertSame(3, $progress['completed_count']);
        $this->assertSame('Build completed', $progress['status_label']);
        $this->assertNull($progress['current_stage']);
        $this->assertSame(['complete', 'complete', 'complete'], array_column($progress['stages'], 'state'));
    }

    public function test_unknown_template_has_no_progress(): void
    {
        $progress = (new BuildProgress)->forBuild(new ImageBuild([
            'target' => 'pmx-unknown',
            'status' => BuildStatus::Running,
        ]));

        $this->assertFalse($progress['available']);
    }
}
