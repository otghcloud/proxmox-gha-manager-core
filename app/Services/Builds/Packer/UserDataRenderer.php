<?php

namespace App\Services\Builds\Packer;

use App\Exceptions\ProvisioningException;

/**
 * Renders the cloud-init autoinstall file Packer serves over HTTP during an Ubuntu build.
 *
 * The published templates ship only `user-data.tpl`; the rendered file carries a password hash
 * and is therefore never distributed.
 */
class UserDataRenderer
{
    public function render(string $templateDirectory, string $username, string $password): void
    {
        $source = $templateDirectory.'/http/user-data.tpl';

        if (! is_readable($source)) {
            return;
        }

        if ($username === '' || $password === '') {
            throw new ProvisioningException('An SSH username and password are required to render the autoinstall file.');
        }

        $contents = file_get_contents($source);

        if ($contents === false) {
            throw new ProvisioningException('The autoinstall template could not be read: '.$source);
        }

        $rendered = strtr($contents, [
            '${RUNNER_USERNAME}' => $username,
            '${RUNNER_PASSWORD_HASH}' => $this->passwordHash($password),
        ]);

        $destination = $templateDirectory.'/http/user-data';

        if (file_put_contents($destination, $rendered) === false) {
            throw new ProvisioningException('The autoinstall file could not be written: '.$destination);
        }

        chmod($destination, 0600);
    }

    private function passwordHash(string $password): string
    {
        $salt = substr(strtr(base64_encode(random_bytes(16)), '+', '.'), 0, 16);
        $hash = crypt($password, '$6$'.$salt);

        if (! is_string($hash) || ! str_starts_with($hash, '$6$')) {
            throw new ProvisioningException('This platform cannot produce SHA-512 crypt hashes.');
        }

        return $hash;
    }
}
