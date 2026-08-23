<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Action;

use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Logging\DebugLogger;

final class CloudflarePurgeAction {

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

	public function handleSucceeded(): void {
		$settings = $this->settings->get();
		if ( $settings['cloudflare_enabled_success'] ) {
			$this->purge( $settings );
		}
	}

	public function handleFailed(): void {
		$settings = $this->settings->get();
		if ( $settings['cloudflare_enabled_failure'] ) {
			$this->purge( $settings );
		}
	}

	private function purge( array $settings ): void {
		$zoneId = $settings['cloudflare_zone_id'];
		$token  = $settings['cloudflare_api_token'];
		if ( $zoneId === '' || $token === '' ) {
			$this->logger->log( 'Cloudflare purge skipped: zone ID or API token is not configured.' );

			return;
		}

		$response = wp_remote_post(
			"https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache",
			array(
				'headers' => array(
					'Authorization' => "Bearer {$token}",
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'purge_everything' => true ) ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->log( sprintf( 'Cloudflare purge request failed: %s', $response->get_error_message() ) );

			return;
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$success = is_array( $body ) && ! empty( $body['success'] );
		$this->logger->log(
			sprintf(
				'Cloudflare purge %s (status %d)',
				$success ? 'succeeded' : 'failed',
				wp_remote_retrieve_response_code( $response )
			)
		);
	}
}
