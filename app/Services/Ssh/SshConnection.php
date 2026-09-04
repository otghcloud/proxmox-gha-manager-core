<?php

namespace App\Services\Ssh;

use App\Exceptions\RemoteException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

/**
 * Thin wrapper over phpseclib, modelled on aurora-manage's SSHService.
 *
 * Separate SSH and SFTP connections are opened lazily and cached, sharing one authentication
 * path so key-based auth can replace passwords later without touching callers.
 */
class SshConnection
{
    private ?SSH2 $ssh = null;

    private ?SFTP $sftp = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly ?string $password = null,
        private readonly ?string $privateKey = null,
        private readonly ?string $passphrase = null,
        private readonly int $timeout = 60,
    ) {}

    /**
     * Write string content to a remote path.
     */
    public function putString(string $remotePath, string $content): self
    {
        if ($this->sftp()->put($remotePath, $content) === false) {
            throw new RemoteException("Could not write {$remotePath} on {$this->host}");
        }

        return $this;
    }

    public function putFile(string $remotePath, string $localPath): self
    {
        if (! is_readable($localPath) || $this->sftp()->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE) === false) {
            throw new RemoteException("Could not upload {$localPath} to {$this->host}:{$remotePath}");
        }

        return $this;
    }

    public function chmod(int $mode, string $remotePath): self
    {
        $this->sftp()->chmod($mode, $remotePath);

        return $this;
    }

    public function delete(string $remotePath): self
    {
        $this->sftp()->delete($remotePath, false);

        return $this;
    }

    /**
     * Run one or more commands in order, returning their combined output.
     *
     * @param  array<int, string>|string  $commands
     */
    public function run(array|string $commands): string
    {
        $ssh = $this->ssh();
        $output = [];

        foreach ((array) $commands as $command) {
            $output[] = trim((string) $ssh->exec($command));
        }

        return implode("\n", $output);
    }

    /**
     * Exit status of the most recently executed command.
     */
    public function exitCode(): int|false
    {
        return $this->ssh?->getExitStatus() ?? false;
    }

    public function disconnect(): void
    {
        $this->ssh?->disconnect();
        $this->sftp?->disconnect();

        $this->ssh = null;
        $this->sftp = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function ssh(): SSH2
    {
        return $this->ssh ??= $this->authenticate(new SSH2($this->host, $this->port, $this->timeout));
    }

    private function sftp(): SFTP
    {
        return $this->sftp ??= $this->authenticate(new SFTP($this->host, $this->port, $this->timeout));
    }

    /**
     * @template T of SSH2
     *
     * @param  T  $connection
     * @return T
     */
    private function authenticate(SSH2 $connection): SSH2
    {
        $credential = $this->privateKey !== null && $this->privateKey !== ''
            ? PublicKeyLoader::load($this->privateKey, $this->passphrase ?: false)
            : $this->password;

        if (! $connection->login($this->username, $credential)) {
            throw new RemoteException("SSH authentication failed for {$this->username}@{$this->host}");
        }

        $connection->setTimeout($this->timeout);

        return $connection;
    }
}
