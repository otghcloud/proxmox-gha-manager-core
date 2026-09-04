<?php

namespace App\Services\Credentials;

use App\Enums\PoolOs;
use App\Models\Credential;
use App\Services\SettingsRepository;
use phpseclib3\Crypt\RSA;

class DefaultCredentialService
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function ensureLinuxCredential(): Credential
    {
        $credential = Credential::query()
            ->where('os', PoolOs::Linux->value)
            ->where('name', 'Default Linux SSH')
            ->first();

        if ($credential !== null && $credential->hasAuthenticationMaterial() && filled($credential->password)) {
            return $credential;
        }

        $key = RSA::createKey(4096);

        return Credential::updateOrCreate(
            ['name' => 'Default Linux SSH', 'os' => PoolOs::Linux->value],
            [
                'username' => $this->settings->defaultRunnerUsername(),
                'password' => bin2hex(random_bytes(32)),
                'private_key' => $key->toString('PKCS8'),
                'public_key' => $key->getPublicKey()->toString('OpenSSH'),
            ],
        );
    }
}
