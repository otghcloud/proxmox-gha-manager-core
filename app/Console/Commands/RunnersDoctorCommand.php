<?php

namespace App\Console\Commands;

use App\Models\Pool;
use App\Services\Provisioning\EnvironmentServices;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunnersDoctorCommand extends EnvironmentCommand
{
    protected $signature = 'runners:doctor {--environment= : Limit the checks to one environment slug}';

    protected $description = 'Validate configuration, credentials and connectivity';

    public function handle(EnvironmentServices $services): int
    {
        $failed = false;

        foreach ($this->environments() as $environment) {
            $this->components->info("Environment: {$environment->name} ({$environment->slug})");

            $checks = [
                $this->check('State database', fn () => DB::connection()->getPdo() !== null
                    ? config('database.default')
                    : throw new \RuntimeException('unreachable')),
                $this->check('Proxmox API', function () use ($services, $environment): string {
                    $vms = $services->proxmox($environment)->clusterVms();

                    return count($vms).' VMs visible, TLS verification '
                        .'for '.$environment->githubAccount->login;
                }),
                $this->check('GitHub API', function () use ($services, $environment): string {
                    $runners = $services->github($environment)->listRunners();

                    return count($runners).' runners registered to '.$environment->githubAccount->login;
                }),
            ];

            foreach ($environment->pools()->with('runnerTemplate')->get() as $pool) {
                $checks[] = $this->check("Pool {$pool->name}", fn (): string => $this->describePool($pool, $services, $environment));
            }

            $this->table(['Check', 'Status', 'Detail'], array_map(
                fn (array $check): array => [
                    $check['name'],
                    $check['passed'] ? '<fg=green>OK</>' : '<fg=red>FAIL</>',
                    $check['detail'],
                ],
                $checks
            ));

            $failed = $failed || collect($checks)->contains(fn (array $check): bool => ! $check['passed']);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function describePool(Pool $pool, EnvironmentServices $services, $environment): string
    {
        if (! $pool->enabled) {
            return 'disabled';
        }

        $template = $pool->runnerTemplate;

        if ($template === null) {
            throw new \RuntimeException('no template linked');
        }

        $vms = $services->proxmox($environment)->clusterVms();

        $mapping = $template->targetMappings()->where('proxmox_targets.id', $services->target($environment)->id)->first();

        if ($mapping === null || ! isset($vms[$mapping->pivot->template_vmid])) {
            throw new \RuntimeException("template VMID for {$template->name} not found");
        }

        if ($template->credential === null || ! $template->credential->hasAuthenticationMaterial()) {
            throw new \RuntimeException('no runner credential configured');
        }

        return count($pool->labels).' labels, max '.$pool->totalMaxConcurrent().', template '.$mapping->pivot->template_vmid;
    }

    /**
     * @return array{name: string, passed: bool, detail: string}
     */
    private function check(string $name, callable $probe): array
    {
        try {
            return ['name' => $name, 'passed' => true, 'detail' => (string) $probe()];
        } catch (Throwable $e) {
            return ['name' => $name, 'passed' => false, 'detail' => $e->getMessage()];
        }
    }
}
