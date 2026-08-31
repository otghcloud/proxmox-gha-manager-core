<?php

namespace App\Services\GitHub;

use App\Exceptions\GitHubException;
use App\Models\GitHubAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GitHubClient
{
    private const API_VERSION = '2022-11-28';

    public function __construct(private readonly GitHubAccount $account) {}

    /**
     * Mint a single-use JIT configuration.
     *
     * The blob is consumed by the runner on its first job, so the long-lived token never
     * has to leave the controller.
     *
     * @param  array<int, string>  $labels
     */
    public function generateJitConfig(string $runnerName, array $labels): JitRunner
    {
        $response = $this->request()->post($this->url($this->runnerPath('/actions/runners/generate-jitconfig')), [
            'name' => $runnerName,
            'runner_group_id' => $this->account->github_runner_group_id,
            'labels' => array_values($labels),
            'work_folder' => $this->account->github_work_folder,
        ]);

        if ($response->failed()) {
            throw new GitHubException("Could not mint a JIT config for {$runnerName}: HTTP {$response->status()} ".trim($response->body()));
        }

        $encoded = $response->json('encoded_jit_config');
        $runnerId = $response->json('runner.id');

        if (! is_string($encoded) || ! is_numeric($runnerId)) {
            throw new GitHubException("GitHub returned an unexpected JIT config payload for {$runnerName}");
        }

        return new JitRunner((int) $runnerId, $runnerName, $encoded);
    }

    /**
     * Every runner registered against the organisation, keyed by runner name.
     *
     * @return array<string, GitHubRunner>
     */
    public function listRunners(): array
    {
        $runners = [];
        $page = 1;

        do {
            $response = $this->request()->get($this->url($this->runnerPath('/actions/runners')), [
                'per_page' => 100,
                'page' => $page,
            ]);

            if ($response->failed()) {
                throw new GitHubException("Could not list runners: HTTP {$response->status()} ".trim($response->body()));
            }

            $batch = $response->json('runners') ?? [];

            foreach ($batch as $payload) {
                $runner = GitHubRunner::fromArray($payload);
                $runners[$runner->name] = $runner;
            }

            $page++;
        } while (count($batch) === 100);

        return $runners;
    }

    /**
     * Current status of a workflow job, used to confirm a runner actually claimed it.
     */
    public function jobStatus(string $repositoryFullName, int $jobId): string
    {
        $response = $this->request()->get($this->url("/repos/{$repositoryFullName}/actions/jobs/{$jobId}"));

        if ($response->failed()) {
            throw new GitHubException("Could not read job {$jobId}: HTTP {$response->status()} ".trim($response->body()));
        }

        return (string) $response->json('status', 'unknown');
    }

    /**
     * The raw log for a finished job. GitHub redirects to a short-lived blob URL, and returns 410
     * once its own retention window has expired.
     */
    public function jobLog(string $repositoryFullName, int $jobId): ?string
    {
        $response = $this->request()
            ->timeout(120)
            ->get($this->url("/repos/{$repositoryFullName}/actions/jobs/{$jobId}/logs"));

        if (in_array($response->status(), [404, 410], true)) {
            return null;
        }

        if ($response->failed()) {
            throw new GitHubException("Could not read the log for job {$jobId}: HTTP {$response->status()} ".trim($response->body()));
        }

        return $response->body();
    }

    /**
     * Deregister a runner. A 404 means it already removed itself, which is the happy path.
     */
    public function deleteRunner(int $runnerId): void
    {
        $response = $this->request()->delete($this->url($this->runnerPath("/actions/runners/{$runnerId}")));

        if ($response->status() === 404 || $response->successful()) {
            return;
        }

        throw new GitHubException("Could not delete runner {$runnerId}: HTTP {$response->status()} ".trim($response->body()));
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) $this->account->github_token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => self::API_VERSION,
            ])
            ->timeout(30);
    }

    private function url(string $path): string
    {
        return rtrim($this->account->github_api_url, '/').$path;
    }

    private function runnerPath(string $path): string
    {
        return $this->account->account_type === 'user'
            ? '/user'.$path
            : "/orgs/{$this->account->login}".$path;
    }
}
