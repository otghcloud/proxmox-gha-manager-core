<?php

namespace Tests\Unit;

use App\Exceptions\ProvisioningException;
use App\Services\Builds\Packer\UserDataRenderer;
use Tests\TestCase;

class UserDataRendererTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/user-data-'.bin2hex(random_bytes(4));
        mkdir($this->directory.'/http', 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/http/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory.'/http');
        rmdir($this->directory);

        parent::tearDown();
    }

    public function test_it_renders_the_username_and_a_sha512_password_hash(): void
    {
        file_put_contents(
            $this->directory.'/http/user-data.tpl',
            "identity:\n  username: \${RUNNER_USERNAME}\n  password: \"\${RUNNER_PASSWORD_HASH}\"\n"
        );

        (new UserDataRenderer)->render($this->directory, 'packer', 'correct horse battery staple');

        $rendered = file_get_contents($this->directory.'/http/user-data');

        $this->assertStringContainsString('username: packer', $rendered);
        $this->assertMatchesRegularExpression('/password: "\$6\$[^"]+"/', $rendered);
        $this->assertStringNotContainsString('correct horse battery staple', $rendered);
        $this->assertStringNotContainsString('${RUNNER_', $rendered);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->directory.'/http/user-data')), -4));
    }

    public function test_it_does_nothing_when_the_template_is_absent(): void
    {
        (new UserDataRenderer)->render($this->directory, 'packer', 'secret');

        $this->assertFileDoesNotExist($this->directory.'/http/user-data');
    }

    public function test_it_rejects_empty_credentials(): void
    {
        file_put_contents($this->directory.'/http/user-data.tpl', 'username: ${RUNNER_USERNAME}');

        $this->expectException(ProvisioningException::class);

        (new UserDataRenderer)->render($this->directory, 'packer', '');
    }
}
