<?php

namespace Tests\Unit;

use App\Exceptions\ProvisioningException;
use App\Services\Builds\Packer\RunnerImagesLocator;
use App\Services\Builds\Packer\TemplateCatalogEntry;
use Tests\TestCase;

class RunnerImagesLocatorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/runner-images-'.bin2hex(random_bytes(4));
        mkdir($this->directory, 0755, true);

        config(['builds.image_builder_path' => $this->directory]);
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

    private function writeCatalog(?string $commit): void
    {
        $catalog = ['templates' => []];

        if ($commit !== null) {
            $catalog['runner_images_commit'] = $commit;
        }

        file_put_contents($this->directory.'/templates.json', json_encode($catalog, JSON_THROW_ON_ERROR));
    }

    public function test_a_bundled_tree_needs_no_override(): void
    {
        $this->writeCatalog('259c79eaf7bb3dde3d88a843bc4a4c57ea342d20');
        mkdir($this->directory.'/vendor/runner-images/images/ubuntu-slim', 0755, true);

        $this->assertNull((new RunnerImagesLocator)->scriptsRoot($this->ubuntuSlim()));
    }

    public function test_windows_targets_have_no_override_to_offer(): void
    {
        $this->writeCatalog(null);

        $this->assertNull((new RunnerImagesLocator)->scriptsRoot(new TemplateCatalogEntry([
            'builders' => ['packer' => ['provisioner' => ['scripts_root_required' => false, 'runner_images_directory' => 'images/windows']]],
        ])));
    }

    public function test_it_fails_when_nothing_is_bundled_and_no_commit_is_pinned(): void
    {
        $this->writeCatalog(null);

        $this->expectException(ProvisioningException::class);

        (new RunnerImagesLocator)->scriptsRoot($this->ubuntuSlim());
    }

    private function ubuntuSlim(): TemplateCatalogEntry
    {
        return new TemplateCatalogEntry([
            'builders' => ['packer' => ['provisioner' => ['scripts_root_required' => true, 'runner_images_directory' => 'images/ubuntu-slim']]],
        ]);
    }
}
