<img src="https://otgh-static-assets.s3.otgh.cloud/branding/logos/otgh_cloud_2024.png" alt="OTGH Cloud" width="200px" />

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

The image bundles the published [proxmox-gha-manager-templates](https://github.com/otghcloud/proxmox-gha-manager-templates) catalog at `/opt/image-builder`, pinned by the `TEMPLATES_REF` build argument, and runs Nginx, PHP-FPM, Redis, queue workers, build workers, and the scheduler under Supervisor. Builds invoke Packer directly against those templates; no external build script is required.

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
