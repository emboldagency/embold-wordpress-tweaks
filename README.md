# Tweaks and Changes to WordPress

There are common changes we normally have to make in every generic WordPress website.

1. Allow SVG uploads in the admin panel.
2. Local only: Defer and async various Gutenberg scripts to avoid Coders 502 errors.
3. Local only: Disable all "wp_mail" functions so Mailgun can't randomly mass email users. This will also break the test
email sent out from local.
4. Disable the XML-RPC functionality.

## Requirements

Define the 'WP_ENVIRONMENT_TYPE' as 'development' in your local wp-config.php

```php
define('WP_ENVIRONMENT_TYPE', 'development');
```

## Enabling local Mailgun 

Comment out or change the 'WP_ENVIRONMENT_TYPE' to not be 'development'. This will let you send emails from local including
the test email.