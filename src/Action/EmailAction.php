<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Action;

use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Logging\DebugLogger;
use NExT\StaaticActions\Support\PlaceholderTemplate;
use NExT\StaaticActions\Support\PublicationPayload;

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

	/**
	 * Sends using the current saved settings regardless of the
	 * success/failure enable toggles, so the configuration can be
	 * verified from the admin UI before turning it on.
	 */
	public function sendTest(): array {
		return $this->send( $this->settings->get(), PublicationPayload::sample() );
	}

	private function send( array $settings, array $context ): array {
		$recipients = array_filter( array_map( 'trim', explode( ',', $settings['email_recipients'] ) ) );
		if ( empty( $recipients ) ) {
			$message = 'Email skipped: no recipients configured.';
			$this->logger->log( $message );

			return array(
				'success' => false,
				'message' => $message,
			);
		}

		$subject = PlaceholderTemplate::render( $settings['email_subject'], $context );
		$body    = PlaceholderTemplate::render( $settings['email_body'], $context );

		$sent    = wp_mail( $recipients, $subject, $body );
		$message = sprintf(
			'Email %s to %s',
			$sent ? 'sent' : 'failed to send',
			implode( ', ', $recipients )
		);
		$this->logger->log( $message );

		return array(
			'success' => $sent,
			'message' => $message,
		);
	}
}
