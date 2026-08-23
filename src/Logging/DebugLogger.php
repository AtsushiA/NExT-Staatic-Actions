<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Logging;

use NExT\StaaticActions\Admin\Settings;

final class DebugLogger
{
    /** @var Settings */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function log(string $message): void
    {
        if (!$this->settings->get()['debug_log_enabled']) {
            return;
        }
        error_log('[NExT Staatic Actions] ' . $message);
    }
}
