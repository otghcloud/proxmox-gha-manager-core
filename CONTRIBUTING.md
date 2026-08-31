<img src="https://otgh-static-assets.s3.otgh.cloud/branding/logos/otgh_cloud_2024.png" alt="OTGH Cloud" width="200px" />

# Contributing

Thank you for your interest in contributing to this project.

You can help this project in multiple ways, whether it be by contributing new features and fixes, or raising issues for things that aren't working quite the way they should.

This document provides some general guidance before opening your first issue or pull request.

---

## Table of Contents

- [Repository Layout](#repository-layout)
- [Branching](#branching)
- [Development](#development)
- [Code Style](#code-style)
- [Commit Messages](#commit-messages)
- [Issues](#issues)
- [Pull Request Titles](#pull-request-titles)

---

## Repository Layout

This is the core Laravel application for the Proxmox GHA Manager: it manages GitHub accounts,
Proxmox nodes, runner templates, pools, and the runner lifecycle.

| Directory | Contents |
| --- | --- |
| `app/` | Application code: models, controllers, services, jobs, Artisan commands |
| `config/` | Laravel configuration, including `config/builds.php` (image builder settings) |
| `resources/` | Blade views, Sass, and JavaScript assets |
| `routes/` | HTTP and console routes |
| `tests/` | PHPUnit test suite |
| `docker/` | Production Docker image (nginx, PHP-FPM, Redis, Supervisor) |

This repo consumes the published [proxmox-gha-manager-templates][templates] artifact for the
Proxmox Packer templates it builds from. Point `IMAGE_BUILDER_PATH` at a checkout of that repo
locally; the Docker image fetches it at build time.

## Branching

Work on a feature branch and open a PR targeting `main`.

We recommend creating a separate branch for each distinct type of change you're making.

## Development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan test
npm run build
```

The unit tests do not talk to Proxmox or GitHub. Anything that does needs a real environment, so
please describe what you tested manually in your pull request.

## Code Style

To keep our codebase clean and predictable, our workflows enforce code practices within this repository.

- **PHP**: Formatted with Laravel Pint (`./vendor/bin/pint --test`) and tested with PHPUnit (`php artisan test`).
- **Markdown**: Validated with `markdownlint-cli2` (config at `.github/rules/.markdownlint.jsonc`).

All linters run automatically on pull requests and where applicable the CI workflow applies and auto-commits fixes.

You do not need to run these linters locally, but doing so before pushing will save you a round trip.

## Commit Messages

Individual commit messages within a PR follow the same prefix convention and
format as PR titles. Squash commits inherit the PR title, so keeping them
consistent is the most important thing.

## Issues

You can submit issues or enhancement requests [by visiting our issues page](https://github.com/otghcloud/proxmox-gha-manager-core/issues).

## Pull Request Titles

Every pull request title **must** follow this format:

```text
<prefix>(<optional-scope>): <Description starting with a capital letter>
```

- The scope is optional and free-form (e.g. a subsystem or area of the codebase).
- A colon and a single space separate the prefix from the description.
- The description starts with a **capital letter**.
- The prefix and scope are **always lowercase**.
- **No trailing period.**

### Allowed Prefixes

| Prefix | When to use |
| --- | --- |
| `build:` | Build system, dependencies, or packaging |
| `chore:` | Maintenance tasks that do not fit any other category |
| `ci:` | Changes limited to GitHub Actions workflows or CI scripts |
| `docs:` | Documentation-only changes |
| `feat:` | A new user-facing feature or capability |
| `fix:` | Something was broken and is now corrected |
| `improve:` | An enhancement to existing behavior that is neither a bug fix nor a new feature |
| `refactor:` | Internal restructuring with no behavior change |
| `style:` | Cosmetic or formatting changes with no logic impact |
| `test:` | Test additions or corrections only |

### Examples

```text
feat: Add per-node build concurrency limits
fix(reaper): Skip VMIDs inside a node's template range
docs: Clarify node draining behaviour
ci: Add PR title validation workflow
```

## Why This Matters

Following the conventions above helps keep our codebase tidy and readable.

Our release workflow depends on structured commit/pull request titles for accurately producing version numbers and release notes.

The CI process enforces these practices and will reject invalid submissions.

[templates]: https://github.com/otghcloud/proxmox-gha-manager-templates
