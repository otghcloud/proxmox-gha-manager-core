<?php

namespace Tests\Unit;

use App\Services\Builds\Packer\TemplateCatalog;
use App\Services\Builds\Packer\TemplateCatalogEntry;
use Tests\TestCase;

class TemplateCatalogTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/template-catalog-'.bin2hex(random_bytes(4));
        mkdir($this->directory.'/templates/ubuntu/ubuntu-slim/packer', 0755, true);

        config(['builds.image_builder_path' => $this->directory]);

        file_put_contents($this->directory.'/templates.json', json_encode([
            'schema_version' => 2,
            'runner_images_commit' => '259c79eaf7bb3dde3d88a843bc4a4c57ea342d20',
            'templates' => [[
                'id' => 'ubuntu-slim',
                'name' => 'Ubuntu Slim',
                'builders' => [
                    'cloudimg' => [
                        'buildable' => false,
                        'disabled_reason' => 'Cloud image builds are not yet supported.',
                        'type' => 'cloudimg',
                        'path' => 'templates/ubuntu/ubuntu-slim/cloudimg',
                        'build_manifest' => 'templates/ubuntu/ubuntu-slim/cloudimg/build.json',
                        'provisioner' => ['runner_images_directory' => 'images/ubuntu-slim', 'scripts_root_required' => true],
                    ],
                    'packer' => [
                        'buildable' => true,
                        'disabled_reason' => null,
                        'type' => 'packer',
                        'path' => 'templates/ubuntu/ubuntu-slim/packer',
                        'build_manifest' => 'templates/ubuntu/ubuntu-slim/packer/build.json',
                        'provisioner' => ['runner_images_directory' => 'images/ubuntu-slim', 'scripts_root_required' => true],
                    ],
                ],
                'template_path' => 'templates/ubuntu/ubuntu-slim',
            ]],
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

    public function test_it_resolves_the_builder_directory_for_a_catalog_id(): void
    {
        $catalog = new TemplateCatalog;
        $entry = $catalog->entryForId('ubuntu-slim');

        $this->assertSame(
            $this->directory.'/templates/ubuntu/ubuntu-slim/packer',
            $catalog->templateDirectory($entry)
        );
    }

    public function test_it_returns_null_for_a_target_that_is_not_installed(): void
    {
        $this->assertNull((new TemplateCatalog)->entryForId('ubuntu-24.04'));
    }

    public function test_it_exposes_the_pinned_runner_images_commit(): void
    {
        $this->assertSame('259c79eaf7bb3dde3d88a843bc4a4c57ea342d20', (new TemplateCatalog)->runnerImagesCommit());
    }

    public function test_packer_is_preferred_while_cloudimg_is_not_buildable(): void
    {
        $entry = (new TemplateCatalog)->entryForId('ubuntu-slim');

        $this->assertSame('packer', $entry->builderName());
        $this->assertSame('images/ubuntu-slim', $entry->runnerImagesDirectory());
        $this->assertTrue($entry->requiresScriptsRoot());
        $this->assertTrue($entry->isBuildable());
        $this->assertNull($entry->disabledReason());
    }

    public function test_a_cloudimg_only_template_is_not_yet_buildable(): void
    {
        $entry = new TemplateCatalogEntry([
            'id' => 'ubuntu-slim',
            'builders' => ['cloudimg' => [
                'buildable' => false,
                'disabled_reason' => 'Cloud image builds are not yet supported.',
                'type' => 'cloudimg',
                'path' => 'templates/ubuntu/ubuntu-slim/cloudimg',
            ]],
        ]);

        $this->assertSame('cloudimg', $entry->builderType());
        $this->assertFalse($entry->isBuildable());
        $this->assertSame('Cloud image builds are not yet supported.', $entry->disabledReason());
    }
}
