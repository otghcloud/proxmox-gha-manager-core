<?php

namespace Tests\Unit;

use App\Contracts\Builds\BuilderInterface;
use App\Contracts\Builds\BuildResult;
use App\Models\ImageBuild;
use App\Services\Builds\BuilderRegistry;
use App\Services\Builds\TemplateCatalogEntry;
use Tests\TestCase;

class BuilderRegistryTest extends TestCase
{
    public function test_the_packer_plugin_registers_with_the_application_registry(): void
    {
        $types = app(BuilderRegistry::class)->types();

        $this->assertContains('packer', $types);
        $this->assertContains('cloudimg', $types);
        $this->assertContains('prebuilt', $types);
    }

    public function test_a_builder_can_be_registered_and_resolved_by_type(): void
    {
        $registry = new BuilderRegistry;
        $builder = new class implements BuilderInterface
        {
            public function type(): string
            {
                return 'test';
            }

            public function build(
                ImageBuild $build,
                TemplateCatalogEntry $entry,
                string $templateDirectory,
            ): BuildResult {
                return new BuildResult(true);
            }
        };

        $registry->register($builder);

        $this->assertSame($builder, $registry->forType('test'));
        $this->assertSame(['test'], $registry->types());
    }

    public function test_duplicate_builder_types_are_rejected(): void
    {
        $registry = new BuilderRegistry;
        $builder = new class implements BuilderInterface
        {
            public function type(): string
            {
                return 'test';
            }

            public function build(
                ImageBuild $build,
                TemplateCatalogEntry $entry,
                string $templateDirectory,
            ): BuildResult {
                return new BuildResult(true);
            }
        };

        $registry->register($builder);

        $this->expectExceptionMessage('A builder is already registered for test.');
        $registry->register($builder);
    }
}
