=== emBold Wordpress Tweaks ===
Contributors: itsjsutxan
Tags: tweaks, improvements
Requires at least: 6.0
Tested up to: 6.3.1
Stable tag: 1.3.0
Requires PHP: 7.4

A collection of our common tweaks and upgrades to WordPress.

== Description ==

# Tweaks and Changes to WordPress

There are common changes we normally have to make in every generic WordPress website.

1. Allow SVG uploads in the admin panel.
2. Local only: Defer and async various Gutenberg scripts to avoid Coders 502 errors.
3. Local only: Disable all "wp_mail" functions so Mailgun can't randomly mass email users. This will also break the test
email sent out from local.
4. Disable XML-RPC for security reasons.
5. Remove line breaks from img tags if litespeed cache plugin is active.
6. Allow searching for posts/pages by slug in the admin panel using the prefix `slug:` before the search term.
7. Adds a slug column to the posts/pages tables in the admin panel.
8. Disables plugin, theme, and file management unless email is our set email. Additional emails can be set in the wp-config.

## To send email on staging/local

Define the 'DISABLE_MAIL' as false in your wp-config.php

`define('DISABLE_MAIL', true);`

## Requirements

Define the 'WP_ENVIRONMENT_TYPE' as 'development', 'staging', or 'production' in the corresponding wp-config.php

`define('WP_ENVIRONMENT_TYPE', 'development');`

== Changelog ==

= 1.3.0 =
* Allow sending email on local/staging with flag

= 1.2.0 =
* Remove Howdy from the admin bar my account menu item

= 1.1.4 =
* Also disable mail when WP_ENVIRONMENT_TYPE is 'maintenance'

= 1.1.3 =
* Don't hide the plugin from the wp cli

= 1.1.2 =
* Swap 'install_plugins' disabled permission for 'update_plugins' disabled permission

= 1.1.1 =
* Also disable mail when WP_ENVIRONMENT_TYPE is staging or local

= 1.1.0 =
* Add info@wphaven.app to default emails
* Add distignore for wp-cli/dist-archive-command

= 1.0.0 =
* Disable plugin, theme, and file editing for accounts that are now designated as super admins via the wp-config

= 0.8.1 =
* fix for search-by-slug sometimes breaking frontend results

= 0.8.0 =
* disable ACF shortcode content escaping

= 0.7.0 =
* disable ACF shortcode content escaping

= 0.6.0 =
* go open source, remove coder async defer scripts

= 0.5.0 =
* skip checking for plugin updates if the API is down

= 0.4.0 =
* show post/page slugs in the admin panel and enable slug search

= 0.3.4 =
* check for is_plugin_active function existing before using it

= 0.3.3 =
* update linebreak removal function

= 0.3.0 =
* remove linebreaks from img tags if litespeed enabled

= 0.2.7 =
* now stop deferring a few scripts

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
