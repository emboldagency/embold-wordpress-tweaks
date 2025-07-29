# Embold WordPress Tweaks

[![Build and Deploy](https://embold.net/api/github/badge/workflow-status.php?repo=embold-wordpress-tweaks&workflow=release.yml)](https://github.com/emboldagency/embold-wordpress-tweaks/actions/workflows/release.yml) <!--
-->![Semantic Versioning](https://embold.net/api/github/badge/semver.php?repo=embold-wordpress-tweaks)

> **Note**: This is the development documentation. WordPress plugins use `readme.txt` for their official plugin information and changelog, not this README.md file.

WordPress plugin that provides common tweaks and changes we normally have to make in every generic WordPress website.

## Features

1. Allow SVG uploads in the admin panel.
2. Local only: Defer and async various Gutenberg scripts to avoid Coders 502 errors.
3. Local only: Disable all "wp_mail" functions so Mailgun can't randomly mass email users. This will also break the test
email sent out from local.
4. Disable the XML-RPC functionality.
5. Remove line breaks from img tags if litespeed cache plugin is active.
6. Allow searching for posts/pages by slug in the admin panel using the prefix 'slug:' before the search term.
7. Adds a slug column to the posts/pages tables in the admin panel.
8. Disables plugin, theme, and file management unless email is our set email. Additional emails can be set in the wp-config.
9. Changes the admin login URL to the value of 'EMBOLD_ADMIN_URL', if set.

## Configuration

## To send email on staging/local

Define the 'DISABLE_MAIL' as false in your wp-config.php

```php
define('DISABLE_MAIL', true);
```

## Requirements

Define the 'WP_ENVIRONMENT_TYPE' as 'development', 'staging', or 'production' in the corresponding wp-config.php

```php
define('WP_ENVIRONMENT_TYPE', 'development');
```

2. Make sure that our user account for the site is set to info@embold.com or info@wphaven.app

## Disable User Account Restrictions

Define 'LOOSE_USER_RESTRICTIONS' in the wp-config and set it to true, this will disable all of our theme, plugin, and
file protections put in place by the plugin.

## Additional Elevated User Accounts

Define the 'ELEVATED_EMAILS' as an array in your local wp-config.php - these account emails will be able to manage plugins
and themes, but they will still be disabled from editing php files directly.

`define('ELEVATED_EMAILS', ['worf@embold.com', 'spock@embold.com']);`

This only needs set on production if we don't have an info@embold.com or info@wphaven.app account there. Then this should be set on production to
whatever our admin email is.

## Enable local Mailgun

Comment out or change the 'WP_ENVIRONMENT_TYPE' to not be 'development'. This will let you send emails from local including
the test email.

## Environment Constants

The plugin supports several environment-specific constants:

- `WP_ENVIRONMENT_TYPE`: Environment type ('development', 'staging', or 'production')
- `DISABLE_MAIL`: Control email functionality (true to disable)
- `LOOSE_USER_RESTRICTIONS`: Disable theme, plugin, and file protections
- `ELEVATED_EMAILS`: Array of additional admin emails


## Development Setup

This project uses Docker Compose for local development.

### Prerequisites

- Docker and Docker Compose
- Git

### Getting Started

1. Clone the repository
2. Start the development environment:
   ```bash
   docker compose up -d
   ```
3. Access WordPress at http://localhost:8080

### WP-CLI Usage

The project includes a persistent CLI container for easier package management:

```bash
# Start the CLI container (if not already running)
docker compose up cli -d

# Run WP-CLI commands
docker compose exec cli wp --info

# Install WP-CLI packages (they persist across restarts)
docker compose exec cli wp package install <package-name>
```

## Building for Distribution

### Building to dist/ Directory

To build the plugin for distribution (excluding development files):

1. Ensure the CLI container is running:
   ```bash
   docker compose up cli -d
   ```

2. Build the plugin to the dist directory:
   ```bash
   # Option 1: Use the build script (recommended)
   ./scripts/build.sh
   
   # Option 2: Manual steps
   # Create distribution archive and extract to dist/
   docker compose exec cli sh -c "cd /var/www/html/wp-content/plugins/embold-wordpress-tweaks && wp dist-archive . /tmp/ --format=zip"
   
   # Set up directory structure
   mkdir -p dist/archives dist/extracted
   
   # Copy versioned archive (replace ${VERSION} with actual version)
   docker cp embold-wordpress-tweaks-cli-1:/tmp/embold-wordpress-tweaks.${VERSION}.zip ./dist/archives/embold-wordpress-tweaks.${VERSION}.zip
   
   # Extract to dist/extracted/
   cd dist/extracted && unzip -q ../archives/embold-wordpress-tweaks.${VERSION}.zip && cd ../..
   ```

3. The build creates this structure:
   ```
   dist/
   ├── archives/
   │   └── embold-wordpress-tweaks-v1.0.0.zip  (WordPress-ready)
   └── extracted/
       ├── embold-wordpress-tweaks.php
       ├── includes/
       └── ... (all plugin files)
   ```

**Note**: On Windows, you can use WSL to run the bash script.

### Creating Distribution Archives

The project includes the `wp dist-archive` command for creating clean distribution archives that respect the `.distignore` file.

#### Creating an Archive

1. Ensure the containers are running:
   ```bash
   docker compose up -d
   ```

2. Create a distribution archive:
   ```bash
   # Navigate to the plugin directory and create archive
   docker compose exec cli sh -c "cd /var/www/html/wp-content/plugins/embold-wordpress-tweaks && wp dist-archive . /tmp/ --format=zip"
   ```

3. Copy the archive to your host machine:
   ```bash
   docker cp embold-wordpress-tweaks-cli-1:/tmp/embold-wordpress-tweaks.${VERSION}.zip .
   ```

The zip format is used as it's the standard format for WordPress plugin installation via the admin interface.

### What Gets Excluded

The `.distignore` file excludes development files such as:
- `.git/` directory and Git files
- `node_modules/` and package management files
- Testing and build configuration files
- Documentation files like `README.md`
- Development scripts and tools

The resulting archive contains only the files needed for production WordPress installation.

## Plugin Structure

- `includes/` - Main plugin source code
- `plugin-update-checker/` - Plugin update functionality
- `scripts/` - Build and deployment scripts (if using Docker setup)
- `dist/` - Distribution builds (excluded from Git except .gitkeep files)
  - `archives/` - Versioned archive files (.zip)
  - `extracted/` - Extracted plugin files ready for deployment
- `docker-compose.yml` - Local development environment
- `Dockerfile.cli` - CLI container Dockerfile with zip utility
- `docker.env` - Docker environment configuration