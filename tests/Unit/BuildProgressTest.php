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
        mkdir($this->directory.'/templates/ubuntu/ubuntu2404/packer', 0755, true);

        config(['builds.image_builder_path' => $this->directory]);

        file_put_contents($this->directory.'/templates.json', json_encode([
            'templates' => [[
                'id' => 'ubuntu-24.04',
                'name' => 'Ubuntu 24.04',
                'builders' => ['packer' => [
                    'buildable' => true,
                    'type' => 'packer',
                    'path' => 'templates/ubuntu/ubuntu2404/packer',
                    'build_manifest' => 'templates/ubuntu/ubuntu2404/packer/build.json',
                    'build_requirements' => ['estimated_minutes' => 75],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($this->directory.'/templates/ubuntu/ubuntu2404/packer/build.json', json_encode([
            'stage_groups' => [
                ['id' => 'configure', 'display_order' => 1, 'stages' => [
                    ['id' => 'prepare', 'name' => 'Prepare', 'marker' => '[image-builder:stage:prepare]'],
                ]],
                ['id' => 'install', 'display_order' => 2, 'stages' => [
                    ['id' => 'install-tools', 'name' => 'Install tools', 'marker' => '[image-builder:stage:install-tools]'],
                ]],
                ['id' => 'cleanup', 'display_order' => 3, 'stages' => [
                    ['id' => 'cleanup', 'name' => 'Cleanup', 'marker' => '[image-builder:stage:cleanup]'],
                ]],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->directory);

        parent::tearDown();
    }

    private function deleteDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $path) {
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }

    public function test_running_build_progress_tracks_seen_stage_markers(): void
    {
        $log = $this->directory.'/build.log';
        file_put_contents($log, "first\n[image-builder:stage:prepare] Prepare\n[image-builder:stage:install-tools] Install tools\n");

        $progress = (new BuildProgress)->forBuild(new ImageBuild([
            'template_catalog_id' => 'ubuntu-24.04',
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
            'template_catalog_id' => 'ubuntu-24.04',
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
            'template_catalog_id' => 'unknown',
            'status' => BuildStatus::Running,
        ]));

        $this->assertFalse($progress['available']);
    }

    public function test_stages_are_grouped_with_a_state_per_group(): void
    {
        $log = $this->directory.'/build.log';
        file_put_contents($log, "[image-builder:stage:prepare] Prepare\n[image-builder:stage:install-tools] Install tools\n");

        $progress = (new BuildProgress)->forBuild(new ImageBuild([
            'template_catalog_id' => 'ubuntu-24.04',
            'status' => BuildStatus::Running,
            'log_path' => $log,
        ]));

        $this->assertSame(['configure', 'install', 'cleanup'], array_column($progress['groups'], 'id'));
        $this->assertSame(['Configure', 'Install', 'Cleanup'], array_column($progress['groups'], 'label'));

        // configure is finished, install holds the current stage, cleanup has not started.
        $this->assertSame(['complete', 'current', 'pending'], array_column($progress['groups'], 'state'));
        $this->assertSame([1, 0, 0], array_column($progress['groups'], 'completed_count'));
        $this->assertSame([1, 1, 1], array_column($progress['groups'], 'stage_count'));
    }

    public function test_every_group_is_complete_once_the_build_succeeds(): void
    {
        $log = $this->directory.'/build.log';
        file_put_contents($log, "[image-builder:stage:prepare] Prepare\n");

        $progress = (new BuildProgress)->forBuild(new ImageBuild([
            'template_catalog_id' => 'ubuntu-24.04',
            'status' => BuildStatus::Succeeded,
            'log_path' => $log,
        ]));

        $this->assertSame(['complete', 'complete', 'complete'], array_column($progress['groups'], 'state'));
    }

    public function test_stages_are_read_from_the_builder_manifest(): void
    {
        $builderDirectory = $this->directory.'/templates/ubuntu/ubuntu-slim/packer';
        mkdir($builderDirectory, 0755, true);

        // The published catalog carries no stages of its own, only a pointer to the builder manifest.
        file_put_contents($this->directory.'/templates.json', json_encode([
            'templates' => [[
                'id' => 'ubuntu-slim',
                'name' => 'Ubuntu Slim',
                'builders' => ['packer' => [
                    'buildable' => true,
                    'type' => 'packer',
                    'path' => 'templates/ubuntu/ubuntu-slim/packer',
                    'build_manifest' => 'templates/ubuntu/ubuntu-slim/packer/build.json',
                    'build_requirements' => ['estimated_minutes' => 25],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($builderDirectory.'/build.json', json_encode([
            'stage_groups' => [
                ['id' => 'configure', 'display_order' => 1, 'stages' => [
                    ['id' => 'prepare-image-generation', 'name' => 'Prepare image generation workspace'],
                    ['id' => 'install-vital-apt-packages', 'name' => 'Install vital apt packages'],
                ]],
            ],
        ], JSON_THROW_ON_ERROR));

        $log = $this->directory.'/build.log';
        file_put_contents($log, "[image-builder:stage:prepare-image-generation] Prepare\n");

        $progress = (new BuildProgress)->forBuild(new ImageBuild([
            'template_catalog_id' => 'ubuntu-slim',
            'status' => BuildStatus::Running,
            'log_path' => $log,
        ]));

        $this->assertTrue($progress['available']);
        $this->assertSame(2, $progress['stage_count']);
        $this->assertSame(25, $progress['estimated_minutes']);
        $this->assertSame('Prepare image generation workspace', $progress['current_stage']['name']);
    }
}
