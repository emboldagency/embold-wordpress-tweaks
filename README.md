# Tweaks and Changes to WordPress

There are common changes we normally have to make in every generic WordPress website.

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

## Change the admin login URL

Update the 'EMBOLD_ADMIN_URL' constant to the new path.