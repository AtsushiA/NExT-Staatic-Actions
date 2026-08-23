# NExT Staatic Actions

*(日本語版 README: [README.md](README.md))*

A WordPress plugin that automatically sends email notifications, purges Cloudflare cache, and fires
webhook requests whenever [Staatic](https://staatic.com/wordpress) (a static site generator plugin)
finishes publishing (deploying) your site.

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

The following placeholders are available in the email and webhook templates:

```
{{status}} {{publication_id}} {{destination_url}} {{entry_url}}
{{date_created}} {{date_finished}} {{num_urls_crawled}} {{num_results_deployed}}
{{user_id}} {{user_login}} {{site_url}} {{admin_publication_url}} {{failure_message}}
```

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

## Uninstall

`uninstall.php` runs when the plugin is deleted and removes the settings option
(`next_staatic_actions_settings`).

## License

GPLv2 or later
