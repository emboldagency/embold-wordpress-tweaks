# Build & Release Guide

## Overview

This document provides guidance for building, testing, and releasing the emBold Wordpress Tweaks plugin.

## Prerequisites

- Git with tags configured
- Composer (for local development)
- Docker & Docker Compose (for local environment)
- WP-CLI (for local building, optional if using Docker)

## Version Management

### Understanding Versions

Versions follow semantic versioning: `MAJOR.MINOR.PATCH` (e.g., `1.5.0`)

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes

### Creating Releases

1. **Write changelog entries** under a `= Unreleased =` heading in `readme.txt` as you work. The build script will rename that heading to the release version during `--fix`.

2. **Update version in files** (or use build script to auto-fix):
   ```bash
   bash scripts/build.sh --fix
   ```
   This updates:
   - Version in `embold-wordpress-tweaks.php` header
   - Stable tag in `readme.txt`
   - Changelog heading: `= Unreleased =` → `= <tag> =` (if present)
   - Version header in `templates/00-suppress-logs.php` (MU-plugin template — stamped with the plugin version that generated it)

3. **Create a Git tag**:
   ```bash
   git tag 1.5.0
   git push origin 1.5.0
   ```

4. **Create prerelease tags** (optional):
   ```bash
   git tag 1.5.0-pre
   git push origin 1.5.0-pre
   ```

## Local Development

### Setting Up

1. Start Docker environment:
   ```bash
   docker compose up -d
   ```

2. Install Composer dependencies:
   ```bash
   composer install
   ```

### Available Scripts

**Linting & Formatting**:
```bash
composer lint          # Check code quality
composer format        # Auto-format code
```

**Building**:
```bash
composer build        # Build production distribution
composer build:dev    # Build without version checks
composer version:fix  # Auto-fix version mismatches
```

## Building

### Production Build

```bash
bash scripts/build.sh
```

**What it does**:
1. Verifies version numbers match Git tag
2. Installs production dependencies
3. Creates distribution ZIP in `dist/archives/`
4. Auto-detects environment (CI, local WP-CLI, or Docker)

### Development Build

```bash
bash scripts/build.sh --dev
```

**Skips version validation** - useful during development.

### Fix Version Mismatches

```bash
bash scripts/build.sh --fix
```

**Auto-updates** `embold-wordpress-tweaks.php`, `readme.txt` (Stable tag + changelog heading), and `templates/00-suppress-logs.php` to match the Git tag. In CI, set `GIT_TAG` instead of relying on the checkout having tags fetched.

## GitHub Actions Release Process

Releases are automatically triggered when you push a Git tag matching:
- Standard releases: `1.5.0`
- Prerelease: `1.5.0-pre`
- Optional v prefix: `v1.5.0`

### Workflow Steps

1. ✅ Checkout code
2. ✅ Set up PHP 8.3, Composer, WP-CLI
3. ✅ Cache Composer dependencies
4. ✅ Install dist-archive WP-CLI command
5. ✅ Run `build.sh --fix` with `GIT_TAG` from `github.ref_name` — syncs file versions, installs prod deps, builds the ZIP
6. ✅ Create GitHub Release with ZIP attached

## Troubleshooting

### Version Mismatch Error

**Error**: `Version Mismatch! Git Tag: 1.5.0, File: 1.4.5`

**Solution**:
```bash
bash scripts/build.sh --fix
git add embold-wordpress-tweaks.php readme.txt templates/00-suppress-logs.php
git commit -m "🔖 Sync version to 1.5.0"
```

### Docker Build Issues

**Issue**: Container not found

**Solution**:
```bash
docker compose ps -q cli    # Check if running
docker compose up cli -d    # Start container
bash scripts/build.sh       # Try build again
```

### Local WP-CLI Not Found

**Issue**: `wp: command not found`

**Solution**: Either install WP-CLI or use Docker:
```bash
docker compose up -d
bash scripts/build.sh  # Will auto-detect Docker
```

## File Structure

```
embold-wordpress-tweaks/
├── scripts/
│   └── build.sh              # Build script (also syncs versions via --fix)
├── docker/
│   ├── Dockerfile.cli        # WP-CLI image
│   ├── docker.env            # Environment config
│   └── docker.env.example    # Example config
├── dist/
│   ├── archives/             # Distribution ZIPs
│   └── extracted/            # Extracted files
├── .github/
│   └── workflows/
│       └── release.yml       # GitHub Actions workflow
├── composer.json             # PHP dependencies
└── embold-wordpress-tweaks.php # Main plugin file
```

## Tips & Best Practices

1. **Always use git tags** for releases - the build system relies on them
2. **Test locally first** using `--dev` mode before creating actual releases
3. **Keep versions synced** - use `build.sh --fix` before tagging
4. **Review the build output** to ensure all steps complete successfully
5. **Test dist-archive output** to ensure the ZIP includes only necessary files
