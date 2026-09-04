<?php

namespace App\Services\Proxmox;

use App\Exceptions\ProxmoxException;
use App\Models\Environment;
use App\Models\ProxmoxTarget;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxmoxClient
{
    private const TASK_POLL_SECONDS = 2;

    private const TASK_TIMEOUT_SECONDS = 600;

    /** Tag applied to every VM this application creates, used for reconciliation. */
    public const MANAGED_TAG = 'gha-runner';

    public const MANAGED_BY = 'pmx-gha-manager';

    public function __construct(private readonly Environment|ProxmoxTarget $connection) {}

    /**
     * Every VM known to the cluster, keyed by VMID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function clusterVms(): array
    {
        $resources = $this->get('/cluster/resources', ['type' => 'vm']);

        $vms = [];

        foreach ($resources as $resource) {
            if (isset($resource['vmid'])) {
                $vms[(int) $resource['vmid']] = $resource;
            }
        }

        return $vms;
    }

    /**
     * Filter a list of cluster resources to those belonging to the given target's node and resource pool or managed runners.
     *
     * @param  array<int, array<string, mixed>>  $clusterVms
     * @return array<int, array<string, mixed>>
     */
    public function filterTargetVms(array $clusterVms, ProxmoxTarget $target): array
    {
        $node = strtolower((string) $target->proxmox_node);
        $pool = $target->proxmox_resource_pool ?: null;

        $targetVms = [];

        foreach ($clusterVms as $vmid => $vm) {
            $vmNode = strtolower((string) ($vm['node'] ?? ''));

            if ($vmNode !== $node) {
                continue;
            }

            if (! empty($vm['template'])) {
                continue;
            }

            $tags = array_map('trim', preg_split('/[;,]/', (string) ($vm['tags'] ?? '')));
            $isManagedTag = in_array(self::MANAGED_TAG, $tags, true);
            $isManagedName = str_starts_with((string) ($vm['name'] ?? ''), 'gha-');
            $isManaged = $isManagedTag || $isManagedName;

            $inPool = $pool !== null && (($vm['pool'] ?? null) === $pool);

            if ($inPool || $isManaged) {
                $vmidKey = (int) ($vm['vmid'] ?? $vmid);
                $targetVms[$vmidKey] = $vm;
            }
        }

        return $targetVms;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(int $vmid): array
    {
        return $this->get("/nodes/{$this->node()}/qemu/{$vmid}/config");
    }

    /**
     * Storages on this node that can hold the given content type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function storages(string $content = 'iso'): array
    {
        return $this->get("/nodes/{$this->node()}/storage", ['content' => $content]);
    }

    /**
     * Return the node-local mount path for a configured storage.
     *
     * Proxmox's `import-from` parameter needs a filesystem path for an ISO
     * volume; an `storage:iso/name` volume ID is rejected by the API.
     */
    public function storagePath(string $storage): string
    {
        $config = $this->get('/nodes/'.$this->node().'/storage/'.$storage);
        $path = $config['path'] ?? null;

        if (is_string($path) && $path !== '') {
            return rtrim($path, '/');
        }

        $type = strtolower((string) ($config['type'] ?? ''));

        if ($type === '') {
            $storageConfig = collect($this->storages())->firstWhere('storage', $storage);
            $type = strtolower((string) (is_array($storageConfig) ? ($storageConfig['type'] ?? '') : ''));
        }

        if (in_array($type, ['cifs', 'nfs', 'glusterfs', 'cephfs'], true)) {
            return '/mnt/pve/'.trim($storage, '/');
        }

        throw new ProxmoxException("Storage {$storage} does not expose a node-local filesystem path.");
    }

    /**
     * Every ISO image available on this node, as Proxmox volume IDs.
     *
     * @return array<int, array{volid: string, storage: string, size: int|null}>
     */
    public function isoImages(): array
    {
        $images = [];

        foreach ($this->storages('iso') as $storage) {
            $name = $storage['storage'] ?? null;

            if (! is_string($name) || ($storage['enabled'] ?? 1) != 1) {
                continue;
            }

            try {
                $contents = $this->get("/nodes/{$this->node()}/storage/{$name}/content", ['content' => 'iso']);
            } catch (ProxmoxException) {
                // A storage can be listed but unreachable; skip it rather than failing the lookup.
                continue;
            }

            foreach ($contents as $item) {
                if (isset($item['volid']) && is_string($item['volid'])) {
                    $images[] = [
                        'volid' => $item['volid'],
                        'storage' => $name,
                        'size' => isset($item['size']) ? (int) $item['size'] : null,
                    ];
                }
            }
        }

        usort($images, fn (array $a, array $b): int => strcmp($a['volid'], $b['volid']));

        return $images;
    }

    /**
     * Download an ISO to the configured node storage and return its Proxmox volume ID.
     */
    public function downloadIso(string $storage, string $url): string
    {
        return $this->downloadImage($storage, $url);
    }

    /**
     * Download an image artifact to node storage and return its volume ID.
     */
    public function downloadImage(string $storage, string $url): string
    {
        $filename = basename((string) parse_url($url, PHP_URL_PATH));

        if ($filename === '' || $filename === '.' || $filename === '/') {
            throw new ProxmoxException("Could not determine an ISO filename from {$url}");
        }

        foreach ($this->isoImages() as $image) {
            if ($image['volid'] === "{$storage}:iso/{$filename}") {
                return $image['volid'];
            }
        }

        $upid = $this->post("/nodes/{$this->node()}/storage/{$storage}/download-url", [
            'content' => 'iso',
            'filename' => $filename,
            'url' => $url,
        ]);

        $this->awaitTask($upid, "download {$filename}");

        return "{$storage}:iso/{$filename}";
    }

    public function downloadCloudImage(string $storage, string $url): string
    {
        $filename = basename((string) parse_url($url, PHP_URL_PATH));

        if ($filename === '' || $filename === '.' || $filename === '/') {
            throw new ProxmoxException("Could not determine a cloud image filename from {$url}");
        }

        $this->downloadImage($storage, $url);

        return $this->storagePath($storage).'/template/iso/'.$filename;
    }

    /**
     * Linked clone from a template. Blocks until the Proxmox task completes.
     */
    public function clone(int $templateVmid, int $vmid, string $name): void
    {
        $payload = array_filter([
            'newid' => $vmid,
            'name' => $name,
            'full' => 0,
            'target' => $this->node(),
            'pool' => $this->connection->proxmox_resource_pool ?: null,
        ], fn ($value): bool => $value !== null);

        $upid = $this->post("/nodes/{$this->node()}/qemu/{$templateVmid}/clone", $payload);

        $this->awaitTask($upid, "clone {$templateVmid} -> {$vmid}");
    }

    /**
     * Create the empty VM shell used before importing a cloud image.
     */
    public function createCloudImageVm(
        int $vmid,
        string $name,
        int $cores,
        int $memory,
        string $networkAdapter,
    ): void {
        $upid = $this->post('/nodes/'.$this->node().'/qemu', [
            'vmid' => $vmid,
            'name' => $name,
            'memory' => $memory,
            'cores' => $cores,
            'sockets' => 1,
            'cpu' => 'host',
            'net0' => $networkAdapter,
            'ostype' => 'l26',
            'machine' => 'q35',
            'scsihw' => 'virtio-scsi-pci',
            'onboot' => 0,
            'agent' => 1,
        ]);

        $this->awaitTask($upid, "create cloud image VM {$vmid}");
    }

    /**
     * Import a cloud image into a SCSI disk and attach the cloud-init drive.
     *
     * The source must be a path or volume visible to the Proxmox node, matching
     * the `import-from` value accepted by the Proxmox API.
     */
    public function importCloudImage(int $vmid, string $storage, string $source): void
    {
        $this->put('/nodes/'.$this->node().'/qemu/'.$vmid.'/config', [
            'scsi0' => sprintf('%s:0,import-from=%s,discard=on,ssd=1', $storage, $source),
            'ide2' => $storage.':cloudinit',
        ]);
    }

    public function resizeCloudImageDisk(int $vmid, string $size): void
    {
        $upid = $this->putReturningTask('/nodes/'.$this->node().'/qemu/'.$vmid.'/resize', [
            'disk' => 'scsi0',
            'size' => $size,
        ]);

        if ($upid !== null) {
            $this->awaitTask($upid, "resize cloud image VM {$vmid}");
        }
    }

    /**
     * Configure credentials and networking before the first cloud-init boot.
     */
    public function configureCloudInit(
        int $vmid,
        string $username,
        ?string $password,
        ?string $publicKey,
        string $ipConfig,
    ): void {
        $payload = [
            'ciuser' => $username,
            'ipconfig0' => $ipConfig,
            'ciupgrade' => 0,
            'boot' => 'order=scsi0',
            'serial0' => 'socket',
        ];

        if ($password !== null && $password !== '') {
            $payload['cipassword'] = $password;
        }

        if ($publicKey !== null && $publicKey !== '') {
            $payload['sshkeys'] = rawurlencode(trim($publicKey));
        }

        $this->put('/nodes/'.$this->node().'/qemu/'.$vmid.'/config', $payload);
    }

    public function convertToTemplate(int $vmid): void
    {
        $upid = $this->post('/nodes/'.$this->node().'/qemu/'.$vmid.'/template');

        $this->awaitTask($upid, "convert cloud image VM {$vmid} to template");
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function configure(int $vmid, int $cores, int $memory, string $poolName, array $metadata, ?string $networkAdapter = null): void
    {
        $payload = [
            'cores' => $cores,
            'memory' => $memory,
            'agent' => 1,
            'tags' => self::MANAGED_TAG.';pool-'.$poolName,
            'description' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ];

        if ($networkAdapter !== null) {
            $payload['net0'] = $networkAdapter;
        }

        try {
            $this->put("/nodes/{$this->node()}/qemu/{$vmid}/config", $payload);
        } catch (ProxmoxException $e) {
            if (! $this->isTagPermissionError($e)) {
                throw $e;
            }

            // Proxmox restricts which tags an API token may create (datacenter user-tag-access).
            // Identification still works from the runner name and description metadata.
            Log::warning('Proxmox rejected the runner tags; continuing without them', [
                'vmid' => $vmid,
                'error' => $e->getMessage(),
            ]);

            unset($payload['tags']);

            $this->put("/nodes/{$this->node()}/qemu/{$vmid}/config", $payload);
        }
    }

    private function isTagPermissionError(ProxmoxException $e): bool
    {
        return str_contains($e->getMessage(), 'HTTP 403') && str_contains($e->getMessage(), 'tag');
    }

    public function start(int $vmid): void
    {
        $upid = $this->post("/nodes/{$this->node()}/qemu/{$vmid}/status/start");

        $this->awaitTask($upid, "start {$vmid}");
    }

    public function stop(int $vmid): void
    {
        $upid = $this->post("/nodes/{$this->node()}/qemu/{$vmid}/status/stop");

        $this->awaitTask($upid, "stop {$vmid}");
    }

    public function destroy(int $vmid): void
    {
        if ($this->status($vmid) !== 'stopped') {
            $this->stop($vmid);
        }

        $upid = $this->delete("/nodes/{$this->node()}/qemu/{$vmid}", [
            'purge' => 1,
            'destroy-unreferenced-disks' => 1,
        ]);

        $this->awaitTask($upid, "destroy {$vmid}");
    }

    public function status(int $vmid): string
    {
        $current = $this->get("/nodes/{$this->node()}/qemu/{$vmid}/status/current");

        return (string) ($current['status'] ?? 'unknown');
    }

    /**
     * First routable IPv4 reported by the QEMU guest agent, or null while it is still booting.
     */
    public function guestIpv4(int $vmid): ?string
    {
        try {
            $result = $this->get("/nodes/{$this->node()}/qemu/{$vmid}/agent/network-get-interfaces");
        } catch (ProxmoxException) {
            // The agent is not answering yet; the caller retries.
            return null;
        }

        foreach ($result['result'] ?? [] as $interface) {
            $name = strtolower((string) ($interface['name'] ?? ''));

            if ($name === 'lo' || str_contains($name, 'loopback')) {
                continue;
            }

            foreach ($interface['ip-addresses'] ?? [] as $address) {
                $ip = (string) ($address['ip-address'] ?? '');

                if (($address['ip-address-type'] ?? '') !== 'ipv4') {
                    continue;
                }

                if ($ip === '' || str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.')) {
                    continue;
                }

                return $ip;
            }
        }

        return null;
    }

    /**
     * Poll a Proxmox task until it stops, treating benign warnings as success.
     */
    private function awaitTask(string $upid, string $description): void
    {
        $deadline = microtime(true) + self::TASK_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            $task = $this->get("/nodes/{$this->node()}/tasks/".rawurlencode($upid).'/status');

            if (($task['status'] ?? '') === 'stopped') {
                $exit = (string) ($task['exitstatus'] ?? '');

                if ($exit === 'OK' || str_starts_with($exit, 'WARNINGS')) {
                    return;
                }

                throw new ProxmoxException("Proxmox task failed ({$description}): {$exit}");
            }

            sleep(self::TASK_POLL_SECONDS);
        }

        throw new ProxmoxException("Timed out waiting for Proxmox task ({$description})");
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function get(string $path, array $query = []): array
    {
        return $this->unwrap($this->request()->get($this->url($path), $query), 'GET '.$path);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload = []): string
    {
        return (string) $this->unwrap($this->request()->asForm()->post($this->url($path), $payload), 'POST '.$path);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function put(string $path, array $payload = []): void
    {
        $this->unwrap($this->request()->asForm()->put($this->url($path), $payload), 'PUT '.$path);
    }

    /**
     * PUT variant for endpoints that return an asynchronous task identifier.
     */
    private function putReturningTask(string $path, array $payload = []): ?string
    {
        $result = $this->unwrap($this->request()->asForm()->put($this->url($path), $payload), 'PUT '.$path);

        return is_string($result) && $result !== '' ? $result : null;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function delete(string $path, array $query = []): string
    {
        $url = $this->url($path);

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return (string) $this->unwrap($this->request()->delete($url), 'DELETE '.$path);
    }

    /**
     * Proxmox wraps every payload in a `data` envelope.
     */
    private function unwrap(Response $response, string $context): mixed
    {
        if ($response->failed()) {
            throw new ProxmoxException("{$context} failed with HTTP {$response->status()}: ".trim($response->body()));
        }

        return $response->json('data');
    }

    private function request(): PendingRequest
    {
        $verify = $this->connection->proxmox_verify_tls
            ? ($this->connection->proxmox_ca_bundle ?: true)
            : false;

        return Http::withHeaders([
            'Authorization' => "PVEAPIToken={$this->connection->proxmox_token_id}={$this->connection->proxmox_token_secret}",
        ])->withOptions(['verify' => $verify])->timeout(30);
    }

    private function url(string $path): string
    {
        return rtrim($this->connection->proxmox_url, '/').$path;
    }

    private function node(): string
    {
        return $this->connection->proxmox_node;
    }
}
