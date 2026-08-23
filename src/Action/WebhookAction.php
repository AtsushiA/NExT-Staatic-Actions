<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Action;

use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Logging\DebugLogger;
use NExT\StaaticActions\Support\PlaceholderTemplate;

final class WebhookAction {

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
		if ( $settings['webhook_enabled_success'] ) {
			$this->send( $settings, $context );
		}
	}

	public function handleFailed( array $context ): void {
		$settings = $this->settings->get();
		if ( $settings['webhook_enabled_failure'] ) {
			$this->send( $settings, $context );
		}
	}

	private function send( array $settings, array $context ): void {
		$url = PlaceholderTemplate::render( $settings['webhook_url'], $context );
		if ( $url === '' ) {
			$this->logger->log( 'Webhook skipped: URL is not configured.' );

			return;
		}

		$headers = $this->parseHeaders( $settings['webhook_headers'], $context );
		$body    = trim( $settings['webhook_body'] ) !== ''
			? PlaceholderTemplate::render( $settings['webhook_body'], $context )
			: wp_json_encode( $context );
		if ( ! isset( $headers['Content-Type'] ) ) {
			$headers['Content-Type'] = 'application/json';
		}

		$response = wp_remote_request(
			$url,
			array(
				'method'  => $settings['webhook_method'],
				'headers' => $headers,
				'body'    => $body,
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->log( sprintf( 'Webhook request failed: %s', $response->get_error_message() ) );

			return;
		}
		$this->logger->log(
			sprintf(
				'Webhook request sent to %s (status %d)',
				$url,
				wp_remote_retrieve_response_code( $response )
			)
		);
	}

	private function parseHeaders( string $raw, array $context ): array {
		$headers = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( $line === '' || strpos( $line, ':' ) === false ) {
				continue;
			}
			[$name, $value]           = explode( ':', $line, 2 );
			$headers[ trim( $name ) ] = PlaceholderTemplate::render( trim( $value ), $context );
		}

		return $headers;
	}
}
