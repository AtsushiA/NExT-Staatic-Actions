<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Action;

use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Logging\DebugLogger;
use NExT\StaaticActions\Support\PlaceholderTemplate;

final class EmailAction {

	/** @var Settings */
	private $settings;

	/** @var DebugLogger */
	private $logger;

	public function __construct( Settings $settings, DebugLogger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function registerHooks(): void {
		add_action( 'next_staatic_actions_publish_succeeded', array( $this, 'handleSucceeded' ) );
		add_action( 'next_staatic_actions_publish_failed', array( $this, 'handleFailed' ) );
	}

	public function handleSucceeded( array $context ): void {
		$settings = $this->settings->get();
		if ( $settings['email_enabled_success'] ) {
			$this->send( $settings, $context );
		}
	}

	public function handleFailed( array $context ): void {
		$settings = $this->settings->get();
		if ( $settings['email_enabled_failure'] ) {
			$this->send( $settings, $context );
		}
	}

	private function send( array $settings, array $context ): void {
		$recipients = array_filter( array_map( 'trim', explode( ',', $settings['email_recipients'] ) ) );
		if ( empty( $recipients ) ) {
			$this->logger->log( 'Email skipped: no recipients configured.' );

			return;
		}

		$subject = PlaceholderTemplate::render( $settings['email_subject'], $context );
		$body    = PlaceholderTemplate::render( $settings['email_body'], $context );

		$sent = wp_mail( $recipients, $subject, $body );
		$this->logger->log(
			sprintf(
				'Email %s to %s',
				$sent ? 'sent' : 'failed to send',
				implode( ', ', $recipients )
			)
		);
	}
}
