<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Logging;

use NExT\StaaticActions\Admin\Settings;

final class DebugLogger {

	/** @var Settings */
	private $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function log( string $message ): void {
		if ( ! $this->settings->get()['debug_log_enabled'] ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- this is the plugin's opt-in debug logger, not leftover debug code.
		error_log( '[NExT Staatic Actions] ' . $message );
	}
}
