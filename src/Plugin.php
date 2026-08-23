<?php

declare(strict_types=1);

namespace NExT\StaaticActions;

use NExT\StaaticActions\Action\CloudflarePurgeAction;
use NExT\StaaticActions\Action\EmailAction;
use NExT\StaaticActions\Action\WebhookAction;
use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Admin\SettingsPage;
use NExT\StaaticActions\Detection\FailureDetector;
use NExT\StaaticActions\Detection\NotificationGuard;
use NExT\StaaticActions\Detection\SuccessDetector;
use NExT\StaaticActions\Logging\DebugLogger;

final class Plugin
{
    public function registerHooks(): void
    {
        $settings = new Settings();
        $logger = new DebugLogger($settings);
        $guard = new NotificationGuard();

        (new SuccessDetector($guard, $logger))->registerHooks();
        (new FailureDetector($guard, $logger))->registerHooks();

        (new EmailAction($settings, $logger))->registerHooks();
        (new CloudflarePurgeAction($settings, $logger))->registerHooks();
        (new WebhookAction($settings, $logger))->registerHooks();

        (new SettingsPage($settings))->registerHooks();
    }
}
