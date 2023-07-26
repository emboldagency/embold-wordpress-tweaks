=== emBold Wordpress Tweaks ===
Contributors: itsjsutxan
Tags: tweaks, improvements
Requires at least: 6.0
Tested up to: 6.2.2
Stable tag: 0.2.6
Requires PHP: 8.0

A collection of our common tweaks and upgrades to WordPress.

== Description ==

# Tweaks and Changes to WordPress

There are common changes we normally have to make in every generic WordPress website.

1. Allow SVG uploads in the admin panel.
2. Local only: Defer and async various Gutenberg scripts to avoid Coders 502 errors.
3. Local only: Disable all "wp_mail" functions so Mailgun can't randomly mass email users. This will also break the test
email sent out from local.
4. Disable XML-RPC for security reasons.

## Requirements

Define the 'WP_ENVIRONMENT_TYPE' as 'development' in your local wp-config.php

`define('WP_ENVIRONMENT_TYPE', 'development');`

## Enabling local Mailgun 

Comment out or change the 'WP_ENVIRONMENT_TYPE' to not be 'development'. This will let you send emails from local including
the test email.

== Changelog ==

= 0.2.6 =
* defer and async some additional scripts when on Coder

= 0.2.5 =
* Disable XML-RPC for security reasons.

= 0.2.4 =
* Trim the value pulled for the GitHub token and fallback if key missing.

= 0.2.3 =
* Plugin update key now stored remotely

= 0.2.2 =
* Add plugin update ability

== Upgrade Notice ==

= 0.2.4 =
* Trim the value pulled for the GitHub token and fallback if key missing.