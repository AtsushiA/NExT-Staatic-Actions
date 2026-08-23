<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Admin;

final class Settings {

	public const OPTION_NAME = NEXT_STAATIC_ACTIONS_SETTINGS_OPTION;

	public static function defaults(): array {
		return array(
			'email_enabled_success'      => false,
			'email_enabled_failure'      => false,
			'email_recipients'           => '',
			'email_subject'              => '[{{site_url}}] Staatic publish {{status}}',
			'email_body'                 => "Publication: {{publication_id}}\nStatus: {{status}}\nDestination: {{destination_url}}\nFinished: {{date_finished}}\n\n{{admin_publication_url}}",

			'cloudflare_enabled_success' => false,
			'cloudflare_enabled_failure' => false,
			'cloudflare_zone_id'         => '',
			'cloudflare_api_token'       => '',

			'webhook_enabled_success'    => false,
			'webhook_enabled_failure'    => false,
			'webhook_url'                => '',
			'webhook_method'             => 'POST',
			'webhook_headers'            => '',
			'webhook_body'               => '',

			'debug_log_enabled'          => false,
		);
	}

	public function get(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * @param mixed $input WordPress passes whatever was posted for this option;
	 *                      it is only an array when the settings form submitted normally.
	 */
	public function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$out      = array();

		$out['email_enabled_success'] = ! empty( $input['email_enabled_success'] );
		$out['email_enabled_failure'] = ! empty( $input['email_enabled_failure'] );
		$out['email_recipients']      = $this->sanitizeEmailList( $input['email_recipients'] ?? '' );
		$out['email_subject']         = sanitize_text_field( $input['email_subject'] ?? $defaults['email_subject'] );
		$out['email_body']            = $this->sanitizeMultiline( $input['email_body'] ?? $defaults['email_body'] );

		$out['cloudflare_enabled_success'] = ! empty( $input['cloudflare_enabled_success'] );
		$out['cloudflare_enabled_failure'] = ! empty( $input['cloudflare_enabled_failure'] );
		$out['cloudflare_zone_id']         = sanitize_text_field( $input['cloudflare_zone_id'] ?? '' );
		$out['cloudflare_api_token']       = sanitize_text_field( $input['cloudflare_api_token'] ?? '' );

		$out['webhook_enabled_success'] = ! empty( $input['webhook_enabled_success'] );
		$out['webhook_enabled_failure'] = ! empty( $input['webhook_enabled_failure'] );
		$out['webhook_url']             = esc_url_raw( $input['webhook_url'] ?? '' );
		$allowedMethods                 = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );
		$method                         = strtoupper( $input['webhook_method'] ?? 'POST' );
		$out['webhook_method']          = in_array( $method, $allowedMethods, true ) ? $method : 'POST';
		$out['webhook_headers']         = $this->sanitizeMultiline( $input['webhook_headers'] ?? '' );
		$out['webhook_body']            = $this->sanitizeMultiline( $input['webhook_body'] ?? '' );

		$out['debug_log_enabled'] = ! empty( $input['debug_log_enabled'] );

		return $out;
	}

	private function sanitizeEmailList( string $raw ): string {
		$parts = preg_split( '/[\r\n,]+/', $raw );
		if ( $parts === false ) {
			$parts = array();
		}
		$valid = array();
		foreach ( $parts as $part ) {
			$email = trim( $part );
			if ( $email !== '' && is_email( $email ) ) {
				$valid[] = $email;
			}
		}

		return implode( ', ', $valid );
	}

	private function sanitizeMultiline( string $raw ): string {
		return implode( "\n", array_map( 'sanitize_text_field', explode( "\n", (string) wp_unslash( $raw ) ) ) );
	}
}
