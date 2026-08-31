<img src="public/default_logo_dark.png" alt="Proxmox GHA Manager" width="200px" />

# Proxmox GHA Manager

Ephemeral, just-in-time GitHub Actions runners backed by Proxmox VE, with a web interface.

## Getting Started

```bash
docker run -d --name proxmox-gha-manager-core \
  --restart=always \
  --network host \
  -v proxmox-gha-manager-core-data:/data \
  -e APP_URL=https://runners.example.com \
  -e TRUSTED_PROXIES='*' \
  ghcr.io/otghcloud/proxmox-gha-manager-core/proxmox-gha-manager-core:latest
```

Open the address in a browser and the setup wizard will guide you through the rest of the setup and configuration.

> [!IMPORTANT]
> The `/data` volume holds the SQLite database **and** the generated `APP_KEY` that encrypts
> every stored Proxmox and GitHub credential. Lose that key and the secrets are unrecoverable.

## Development

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Checks: `./vendor/bin/pint --test` and `vendor/bin/phpunit`.

## Building the image

Build directly from this repository root:

```bash
docker build -t proxmox-gha-manager-core -f docker/Dockerfile .
```

The container automatically clones the required builder and template dependencies into `/opt/image-builder` and runs Nginx, PHP-FPM, Redis, queue workers, build workers, and the scheduler under Supervisor.

## Command line

Every command accepts `--environment=<slug>`.

| Command | Purpose |
| --- | --- |
| `runners:doctor` | Check configuration, credentials and connectivity |
| `runners:list` | Show tracked runner VMs |
| `runners:spawn <pool>` | Provision one runner by hand |
| `runners:destroy <vmid>` | Destroy a VM and deregister its runner |
| `runners:reap` | Run one reconciliation and destruction pass |
| `runners:reconcile` | Re-sync against Proxmox without destroying anything |

## Contributing, License and Security

See: [CONTRIBUTING.md](CONTRIBUTING.md), [LICENSE.md](LICENSE.md), [SECURITY.md](SECURITY.md).
