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

	/**
	 * Purges using the current saved settings right now, regardless of the
	 * success/failure enable toggles.
	 */
	public function purgeNow(): array {
		return $this->purge( $this->settings->get() );
	}

	/**
	 * Confirms the Zone ID / API token can actually reach the Cloudflare
	 * API and access the configured zone, without purging anything.
	 */
	public function verifyConnection(): array {
		$settings = $this->settings->get();
		$zoneId   = $settings['cloudflare_zone_id'];
		$token    = $settings['cloudflare_api_token'];
		if ( $zoneId === '' || $token === '' ) {
			return array(
				'success' => false,
				'message' => __( 'Zone ID または API トークンが未設定です。', 'next-staatic-actions' ),
			);
		}

		$response = wp_remote_get(
			"https://api.cloudflare.com/client/v4/zones/{$zoneId}",
			array(
				'headers' => array(
					'Authorization' => "Bearer {$token}",
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$message = sprintf(
				/* translators: %s: error message */
				__( '接続に失敗しました: %s', 'next-staatic-actions' ),
				$response->get_error_message()
			);
			$this->logger->log( 'Cloudflare verify failed: ' . $response->get_error_message() );

			return array(
				'success' => false,
				'message' => $message,
			);
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$success = is_array( $body ) && ! empty( $body['success'] );

		if ( $success ) {
			$zoneName = $body['result']['name'] ?? $zoneId;
			$message  = sprintf(
				/* translators: %s: Cloudflare zone name */
				__( '接続を確認しました（Zone: %s）。', 'next-staatic-actions' ),
				$zoneName
			);
			$this->logger->log( 'Cloudflare verify succeeded for zone ' . $zoneName );

			return array(
				'success' => true,
				'message' => $message,
			);
		}

		$errorMessage = $this->extractErrorMessage( $body );
		$this->logger->log( 'Cloudflare verify failed: ' . $errorMessage );

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: %s: Cloudflare API error message */
				__( '接続を確認できませんでした: %s', 'next-staatic-actions' ),
				$errorMessage
			),
		);
	}

	private function purge( array $settings ): array {
		$zoneId = $settings['cloudflare_zone_id'];
		$token  = $settings['cloudflare_api_token'];
		if ( $zoneId === '' || $token === '' ) {
			$message = 'Cloudflare purge skipped: zone ID or API token is not configured.';
			$this->logger->log( $message );

			return array(
				'success' => false,
				'message' => __( 'Zone ID または API トークンが未設定です。', 'next-staatic-actions' ),
			);
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

			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'パージに失敗しました: %s', 'next-staatic-actions' ),
					$response->get_error_message()
				),
			);
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

		if ( $success ) {
			return array(
				'success' => true,
				'message' => __( 'キャッシュのパージが完了しました。', 'next-staatic-actions' ),
			);
		}

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: %s: Cloudflare API error message */
				__( 'パージに失敗しました: %s', 'next-staatic-actions' ),
				$this->extractErrorMessage( $body )
			),
		);
	}

	private function extractErrorMessage( $body ): string {
		if ( is_array( $body ) && ! empty( $body['errors'][0]['message'] ) ) {
			return $body['errors'][0]['message'];
		}

		return __( '不明なエラー', 'next-staatic-actions' );
	}
}
