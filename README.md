<img src="https://otgh-static-assets.s3.otgh.cloud/branding/logos/otgh_cloud_2024.png" alt="OTGH Cloud" width="200px" />

# Proxmox Manager

Ephemeral, just-in-time GitHub Actions runners backed by Proxmox VE, with a web interface.

This repository is the core runtime for the new proxmox-gha-manager split. It is the canonical orchestration application and is intended to replace the legacy monorepo-based manager.

## Current capabilities

| Capability | Manager |
| --- | --- |
| Configuration | A database, edited in the browser |
| Scope | GitHub organisations with multiple Proxmox targets |
| History | Every runner and transition retained |

## Getting Started

```bash
docker run -d --name proxmox-manager \
  --restart=always \
  --network host \
  -v proxmox-manager-data:/data \
  -e APP_URL=https://runners.example.com \
  -e TRUSTED_PROXIES='*' \
  ghcr.io/otghcloud/github-actions-proxmox/proxmox-manager:latest
```

Open the address in a browser and the setup wizard takes over: requirements check, external
URL, timezone and an administrator account.

> [!IMPORTANT]
> The `/data` volume holds the SQLite database **and** the generated `APP_KEY` that encrypts
> every stored Proxmox and GitHub credential. Lose that key and the secrets are unrecoverable.

## Development

```bash
composer install
npm install && npm run build
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Checks: `./vendor/bin/pint --test` and `php artisan test`.

## Building the image

The build context is the **repository root**, because the image bundles the Proxmox Packer
templates and the extracted template artifacts from the sibling templates repo:

```bash
cd ..
# ensure the split template repo is available adjacent to this repo
#   ../proxmox-gha-manager-templates

docker build -f proxmox-gha-manager-core/docker/Dockerfile -t proxmox-gha-manager-core .
```

The container runs nginx, PHP-FPM, Redis, four provisioning queue workers, two concurrent build
workers and the scheduler under Supervisor.

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

## Documentation

Full documentation is on the
[documentation website](https://otghcloud.github.io/github-actions-proxmox/proxmox-manager/),
with raw sources in [`docs/proxmox-manager/`](../docs/proxmox-manager/).

The recommended setup order is documented in [Setup Workflow](../docs/proxmox-manager/setup-workflow.md),
and backup guidance is in [Backup and Restore](../docs/proxmox-manager/backup-restore.md).

## Contributing, License and Security

See the repository root: [CONTRIBUTING.md](../CONTRIBUTING.md), [LICENSE.md](../LICENSE.md), [SECURITY.md](../SECURITY.md).
