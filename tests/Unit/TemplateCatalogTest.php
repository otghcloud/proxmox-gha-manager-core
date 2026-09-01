<?php

namespace Tests\Unit;

use App\Services\Builds\Packer\TemplateCatalog;
use Tests\TestCase;

class TemplateCatalogTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/template-catalog-'.bin2hex(random_bytes(4));
        mkdir($this->directory.'/templates/proxmox/ubuntu/ubuntu-slim', 0755, true);

        config(['builds.image_builder_path' => $this->directory]);

        file_put_contents($this->directory.'/templates.json', json_encode([
            'schema_version' => 1,
            'runner_images_commit' => '259c79eaf7bb3dde3d88a843bc4a4c57ea342d20',
            'templates' => [[
                'id' => 'ubuntu-slim-proxmox-x64',
                'target' => 'ubuntu-slim',
                'template_path' => 'templates/proxmox/ubuntu/ubuntu-slim',
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

    public function test_it_resolves_the_template_directory_for_a_catalog_id(): void
    {
        $catalog = new TemplateCatalog;
        $entry = $catalog->entryForId('ubuntu-slim-proxmox-x64');

        $this->assertSame(
            $this->directory.'/templates/proxmox/ubuntu/ubuntu-slim',
            $catalog->templateDirectory($entry)
        );
    }

    public function test_it_returns_null_for_a_target_that_is_not_installed(): void
    {
        $this->assertNull((new TemplateCatalog)->entryForId('ubuntu-24.04-proxmox-x64'));
    }

    public function test_it_exposes_the_pinned_runner_images_commit(): void
    {
        $this->assertSame('259c79eaf7bb3dde3d88a843bc4a4c57ea342d20', (new TemplateCatalog)->runnerImagesCommit());
    }
}
