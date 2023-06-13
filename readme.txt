=== Plugin Name ===
Contributors: itsjsutxan
Tags: tweaks, improvements
Requires at least: 6.0
Tested up to: 6.2.2
Stable tag: 0.2.3
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Here is a short description of the plugin.  This should be no more than 150 characters.  No markup here.

== Description ==

# Tweaks and Changes to WordPress

There are common changes we normally have to make in every generic WordPress website.

1. Allow SVG uploads in the admin panel.
2. Local only: Defer and async various Gutenberg scripts to avoid Coders 502 errors.
3. Local only: Disable all "wp_mail" functions so Mailgun can't randomly mass email users. This will also break the test
email sent out from local.

## Requirements

Define the 'WP_ENVIRONMENT_TYPE' as 'development' in your local wp-config.php

```php
define('WP_ENVIRONMENT_TYPE', 'development');
```

## Enabling local Mailgun 

Comment out or change the 'WP_ENVIRONMENT_TYPE' to not be 'development'. This will let you send emails from local including
the test email.

== Changelog ==

= 0.2.3 =
* Plugin update key now stored remotely

= 0.2.2 =
* Add plugin update ability

== Upgrade Notice ==

= 0.2.3 =
The GitHub token for updating the plugin is no longer stored in the repo or on the server, but remotely on our server.