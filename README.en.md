# NExT Staatic Actions

[![CI](https://github.com/AtsushiA/NExT-Staatic-Actions/actions/workflows/ci.yml/badge.svg)](https://github.com/AtsushiA/NExT-Staatic-Actions/actions/workflows/ci.yml)

*(日本語版 README: [README.md](README.md))*

A WordPress plugin that automatically sends email notifications, purges Cloudflare cache, and fires
webhook requests whenever [Staatic](https://staatic.com/wordpress) (a static site generator plugin)
finishes publishing (deploying) your site. It can also trigger the publish itself on a schedule —
once at a specific date/time, daily, or on selected weekdays.

## Requirements

- WordPress 5.0 or later
- PHP 7.4 or later
- The [Staatic](https://wordpress.org/plugins/staatic/) plugin must be active

## Installation

1. Place this repository at `wp-content/plugins/NExT-Staatic-Actions`
2. Activate "NExT Staatic Actions" from the WordPress admin Plugins list
3. Configure notifications under the Staatic > Actions admin menu

## Features

Whenever a Staatic publish **succeeds** or **fails**, each of the following can be enabled or disabled
independently:

| Feature | Description |
|---|---|
| Email notification | Sends to multiple recipients, with a templated subject and body |
| Cloudflare cache purge | Calls the Purge Cache API using a Zone ID / API token |
| Webhook notification | Sends an HTTP request to any URL (method, headers, and body are configurable) |

The email and webhook sections each have a "Send test with saved settings" button, letting you verify
delivery and see a success/failure status immediately, without waiting for an actual Staatic publish
(this works regardless of whether the enable toggles are on).

The Cloudflare section has two buttons you can use right after saving a Zone ID / API token:

- **Verify connection** — calls the Cloudflare API to confirm the Zone ID and token are valid, without purging anything
- **Purge now** — after a confirmation dialog, actually purges the entire cache using the saved settings

The following placeholders are available in the email and webhook templates:

```
{{status}} {{publication_id}} {{destination_url}} {{entry_url}}
{{date_created}} {{date_finished}} {{num_urls_crawled}} {{num_results_deployed}}
{{user_id}} {{user_login}} {{site_url}} {{admin_publication_url}} {{failure_message}}
```

## Scheduled publishing

Under Staatic's admin menu, on its own "Scheduled Publish" page (separate from "Actions"), you can
enable automatic publishing with one of these modes. Times are evaluated in the site's timezone.

| Mode | Description |
|---|---|
| Once, at a specific date/time | Publishes exactly once at the given date and time (a past date/time is ignored) |
| Daily | Publishes every day at the given time |
| Specific weekdays | Publishes only on the selected weekdays, at the given time |

This works via WP-Cron (`next_staatic_actions_scheduled_publish`) calling Staatic's own publish
trigger, `do_action('staatic_publish')`; saving the settings automatically reschedules it.

**This page alone is accessible to Editor role and above.** The "Actions" page (email, Cloudflare,
webhook settings) remains administrator-only (`manage_options`). Editors can't view or change
anything besides the schedule fields — saving from this page always leaves every other setting
exactly as currently stored in the database.

Whether editors get this access at all is controlled by administrators via a checkbox on the
Actions → Advanced tab (enabled by default). Turning it off blocks editors from the Scheduled
Publish page starting with their next request.

## How detection works

Staatic itself does not expose a dedicated "publish finished" or "publish failed" hook, so this
plugin combines Staatic's internal per-task hooks to detect both outcomes.

- **Success**: hooks `staatic_publication_task_after` and checks whether the task is `FinishTask`,
  the last task in Staatic's pipeline.
- **Failure**: captures a reference to the in-progress `Publication` object via
  `staatic_publication_task_any`, then checks on the `shutdown` action whether its status has become
  `failed` — a workaround since Staatic never fires a hook on failure.

Both work for background publishing (admin screen / WP-Cron) and WP-CLI publishing alike. See
[SPEC.md](SPEC.md) for the detailed design (Japanese).

## Extending

This plugin fires its own action hooks. The built-in email, Cloudflare, and webhook features are
themselves just consumers of these hooks, so other code can hook into the same events:

```php
add_action('next_staatic_actions_publish_succeeded', function (array $context) {
    // $context includes publication_id, status, destination_url, and more
});

add_action('next_staatic_actions_publish_failed', function (array $context) {
    // ...
});
```

## Development

```bash
composer install

# Coding standards check / auto-fix
composer run phpcs
composer run phpcbf

# Unit tests only (no WordPress required)
composer run test:unit

# Full suite including integration tests (requires the WordPress test library)
bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit --bootstrap tests/phpunit/bootstrap.php
```

GitHub Actions runs phpcs, PHPUnit (WP latest + one prior version × PHP 7.4/8.3/8.4), and Plugin Check
on every push/PR to `main`. Pushing a tag in `0.0.0` format builds a distributable zip and creates a
GitHub Release automatically, but only once all CI checks pass (the tag must also match the plugin
header's `Version`).

## Uninstall

`uninstall.php` runs when the plugin is deleted and removes the settings option
(`next_staatic_actions_settings`).

## License

GPLv2 or later
