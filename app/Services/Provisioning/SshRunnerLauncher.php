<?php

namespace App\Services\Provisioning;

use App\Exceptions\RemoteException;
use App\Models\GitHubAccount;
use App\Models\Pool;
use App\Services\Ssh\SshConnection;

class SshRunnerLauncher
{
    private const JIT_FILE = '.jitconfig';

    /**
     * Start the GitHub runner inside a freshly booted guest.
     *
     * The JIT blob is kept out of the SSH command line and shell history. phpseclib offers no way
     * to signal channel EOF,
     * so a reader like `cat` would block forever. Instead the blob is written to a private file
     * and removed by the launch command before the runner starts — the same upload-then-execute
     * pattern aurora-manage uses for its remote scripts.
     */
    public function launch(GitHubAccount $account, Pool $pool, string $host, string $encodedJitConfig, string $runnerName): void
    {
        $ssh = new SshConnection(
            host: $host,
            port: $pool->runnerTemplate->os->remotePort(),
            username: $account->linux_ssh_username,
            password: $account->linux_ssh_password,
        );

        $directory = rtrim($pool->runnerDirectory(), '/');
        $jitPath = $directory.'/'.self::JIT_FILE;

        $ssh->putString($jitPath, $encodedJitConfig)->chmod(0600, $jitPath);

        // Temporary until the templates ship with the SSH user already in the docker group.
        $ssh->run('sudo -n usermod -aG docker '.escapeshellarg($account->linux_ssh_username));

        $ssh->run('sudo -n hostnamectl set-hostname '.escapeshellarg($runnerName));

        $output = $ssh->run($this->launchCommand($directory));

        if ($ssh->exitCode() !== 0) {
            $ssh->delete($jitPath);
            $ssh->disconnect();

            throw new RemoteException("Runner launch failed on {$host}: ".trim($output));
        }

        $ssh->disconnect();
    }

    private function launchCommand(string $directory): string
    {
        return sprintf(
            'cd %s && JITCONFIG="$(cat %s)" && rm -f %s && '
                .'(setsid nohup ./run.sh --jitconfig "$JITCONFIG" > runner-startup.log 2>&1 < /dev/null &) && echo started',
            escapeshellarg($directory),
            escapeshellarg(self::JIT_FILE),
            escapeshellarg(self::JIT_FILE),
        );
    }
}
