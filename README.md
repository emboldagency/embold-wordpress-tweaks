# Embold WordPress Tweaks

[![Build and Deploy](https://embold.net/api/github/badge/workflow-status.php?repo=embold-wordpress-tweaks&workflow=release.yml)](https://github.com/emboldagency/embold-wordpress-tweaks/actions/workflows/release.yml) <!--
-->![Semantic Versioning](https://embold.net/api/github/badge/semver.php?repo=embold-wordpress-tweaks)

> **Note**: This is the development documentation. WordPress plugins use `readme.txt` for their official plugin information and changelog, not this README.md file.

WordPress plugin that provides common tweaks and changes we normally have to make in every generic WordPress website.

## Features

1. Allow SVG uploads in the admin panel.
2. Local only: Defer and async various Gutenberg scripts to avoid Coders 502 errors.
3. Non-prod only: Disable all "wp_mail" functions by default so Mailgun can't randomly mass email users. This will also break the test
   email sent out from local. Override SMTP settings with constants or plugin options.
4. Disable the XML-RPC functionality.
5. Remove line breaks from img tags if litespeed cache plugin is active.
6. Allow searching for posts/pages by slug in the admin panel using the prefix 'slug:' before the search term.
7. Adds a slug column to the posts/pages tables in the admin panel.
8. Disables plugin, theme, and file management unless email is our set email. Additional emails can be set in the wp-config.

## Strict requirements

1. Define the 'WP_ENVIRONMENT_TYPE' as 'development', 'staging', or 'production' in the corresponding wp-config.php

```php
define('WP_ENVIRONMENT_TYPE', 'development');
```

2. Make sure that our user account for the site is set to info@embold.com or info@wphaven.app.

## Disable user account restrictions

To disable all of our theme, plugin, and file protections, allowing any user to make dangerous changes:

```php
define('LOOSE_USER_RESTRICTIONS', true);
```

## Additional elevated user accounts

Define the `ELEVATED_EMAILS` as an array in your local wp-config.php - these account emails will be able to manage plugins
and themes, but they will still be disabled from editing php files directly.

```php
define('ELEVATED_EMAILS', ['worf@embold.com', 'spock@embold.com']);
```

**This only needs set on production if we don't have an info@embold.com or info@wphaven.app account there or if the client requests permissions.**

## Disable/override mail on staging/local

Mail is blocked by default in non-production environments to prevent accidental emailing of real users.

**Default behavior:**

- **Production:** Mail is allowed.
- **Staging/Development:** Mail is blocked.
- **Local:** Mail uses local Mailpit if detected.

### Controlling mail behavior

You can control this behavior using constants in `wp-config.php`.

**Block all mail**
Strictly block all mail, regardless of environment.

```php
define('DISABLE_MAIL', true);
```

**Override with SMTP**
Defining `EMBOLD_SMTP_HOST` will automatically enable "SMTP Override" mode if `DISABLE_MAIL` is not set to true. This is useful for using tools like Mailpit on staging.

```php
define('EMBOLD_SMTP_HOST', 'mailpit');
define('EMBOLD_SMTP_PORT', 1025);
define('EMBOLD_SMTP_FROM_EMAIL', 'admin@local.test');
define('EMBOLD_SMTP_FROM_NAME', 'Local Test');
define('EMBOLD_SMTP_USERNAME', '');
define('EMBOLD_SMTP_PASSWORD', '');
define('EMBOLD_SMTP_SECURE', '');
```

**Allow mail**
If you simply want to allow mail to function normally (using the server's default mailer) on a staging/dev site:

```php
define('DISABLE_MAIL', false);
```

**Precedence order:**

1. `DISABLE_MAIL` (true) -> Block everything.
2. `EMBOLD_SMTP_HOST` (defined) -> Use SMTP settings.
3. `DISABLE_MAIL` (false) -> Allow normal mail flow unless.
4. Plugin Options (Settings Page).
5. Environment Defaults.

## Constants

### Core & security

Control core behavior and security settings via constants in `wp-config.php`:

- `LOOSE_USER_RESTRICTIONS`: Disable theme, plugin, and file protections
- `ELEVATED_EMAILS`: Array of additional admin emails
- `EMBOLD_ALLOW_SVG`: Enable SVG uploads (Warning: Security Risk)
- `EMBOLD_DISABLE_XMLRPC`: Re-enable XML-RPC (Disabled by default)
- `EMBOLD_DISABLE_ACF_ESCAPING`: Disable ACF shortcode escaping

### Performance & debugging

Control debug logging and performance tweaks:

- `EMBOLD_SUPPRESS_LOGS`: Suppress debug logs
- `EMBOLD_SUPPRESS_LOGS_EXTRA`: Array of extra strings to suppress
- `EMBOLD_DEFER_SCRIPTS`: Defer non-critical scripts to avoid 502s
- `EMBOLD_ASYNC_SCRIPTS`: Async admin scripts
- `EMBOLD_CLEAN_IMG_TAGS`: Remove line breaks from img tags (Litespeed Cache)

### Admin interface

Configure admin panel features:

- `EMBOLD_ENABLE_SLUG_SEARCH`: Enable slug search
- `EMBOLD_ENABLE_SLUG_COLUMN`: Enable slug column
- `EMBOLD_REMOVE_HOWDY`: Remove "Howdy" greeting

## Installation through git

From the wp-content/plugins directory:

```bash
git clone git@github.com:emboldagency/embold-wordpress-tweaks.git && \
cd embold-wordpress-tweaks && \
while IFS= read -r p; do [[ -z "$p" || "$p" =~ ^# ]] && continue; rm -rf $p 2>/dev/null; done < .distignore && \
wp plugin activate embold-wordpress-tweaks
```

## Development setup

This project uses Docker Compose for local development.

### Prerequisites

- Docker and Docker Compose
- Git

### Getting started

1. Clone the repository
2. Start the development environment:
   ```bash
   docker compose up -d
   ```
3. Access WordPress at http://localhost:8080

### WP-CLI usage

The project includes a persistent CLI container for easier package management:

```bash
# Start the CLI container (if not already running)
docker compose up cli -d

# Run WP-CLI commands
docker compose exec cli wp --info

# Install WP-CLI packages (they persist across restarts)
docker compose exec cli wp package install <package-name>
```

## Building for distribution

### GitHub Actions (automated)

Every time a new semver tag (e.g., `v1.2.3`) is pushed to the repository, a GitHub Action automatically runs to build and release the plugin.

1.  **Syncs Version**: Updates the plugin file header version to match the git tag.
2.  **Builds Archive**: Uses `wp dist-archive` to create a production-ready ZIP file.
3.  **Creates Release**: Publishes a GitHub Release with the ZIP attached.

You can download the latest build from the [Releases page](https://github.com/emboldagency/embold-wordpress-tweaks/releases).

### Manual build

To manually build the plugin for distribution (excluding development files):

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

### Creating distribution archives

The project includes the `wp dist-archive` command for creating clean distribution archives that respect the `.distignore` file.

#### Creating an archive

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

### What gets excluded

The `.distignore` file excludes development files such as:

- `.git/` directory and Git files
- `node_modules/` and package management files
- Testing and build configuration files
- Documentation files like `README.md`
- Development scripts and tools

The resulting archive contains only the files needed for production WordPress installation.

## Plugin structure

- `includes/` - Main plugin source code
- `plugin-update-checker/` - Plugin update functionality
- `scripts/` - Build and deployment scripts (if using Docker setup)
- `dist/` - Distribution builds (excluded from Git except .gitkeep files)
  - `archives/` - Versioned archive files (.zip)
  - `extracted/` - Extracted plugin files ready for deployment
- `docker-compose.yml` - Local development environment
- `Dockerfile.cli` - CLI container Dockerfile with zip utility
- `docker.env` - Docker environment configuration
