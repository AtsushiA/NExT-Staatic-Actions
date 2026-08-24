<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Admin;

final class Settings {

	public const OPTION_NAME = NEXT_STAATIC_ACTIONS_SETTINGS_OPTION;

	public static function defaults(): array {
		return array(
			'email_enabled_success'          => false,
			'email_enabled_failure'          => false,
			'email_recipients'               => '',
			'email_subject'                  => '[{{site_url}}] Staatic publish {{status}}',
			'email_body'                     => "Publication: {{publication_id}}\nStatus: {{status}}\nDestination: {{destination_url}}\nFinished: {{date_finished}}\n\n{{admin_publication_url}}",

			'cloudflare_enabled_success'     => false,
			'cloudflare_enabled_failure'     => false,
			'cloudflare_zone_id'             => '',
			'cloudflare_api_token'           => '',

			'webhook_enabled_success'        => false,
			'webhook_enabled_failure'        => false,
			'webhook_url'                    => '',
			'webhook_method'                 => 'POST',
			'webhook_headers'                => '',
			'webhook_body'                   => '',

			'schedule_enabled'               => false,
			'schedule_mode'                  => 'daily',
			'schedule_time'                  => '03:00',
			'schedule_date'                  => '',
			'schedule_weekdays'              => array(),
			'schedule_editor_access_enabled' => true,

			'debug_log_enabled'              => false,
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
		$out['email_recipients']      = $this->sanitizeEmailList( $this->str( $input['email_recipients'] ?? '' ) );
		$out['email_subject']         = sanitize_text_field( $this->str( $input['email_subject'] ?? $defaults['email_subject'], $defaults['email_subject'] ) );
		$out['email_body']            = $this->sanitizeMultiline( $this->str( $input['email_body'] ?? $defaults['email_body'], $defaults['email_body'] ) );

		$out['cloudflare_enabled_success'] = ! empty( $input['cloudflare_enabled_success'] );
		$out['cloudflare_enabled_failure'] = ! empty( $input['cloudflare_enabled_failure'] );
		$out['cloudflare_zone_id']         = sanitize_text_field( $this->str( $input['cloudflare_zone_id'] ?? '' ) );
		$out['cloudflare_api_token']       = sanitize_text_field( $this->str( $input['cloudflare_api_token'] ?? '' ) );

		$out['webhook_enabled_success'] = ! empty( $input['webhook_enabled_success'] );
		$out['webhook_enabled_failure'] = ! empty( $input['webhook_enabled_failure'] );
		$out['webhook_url']             = esc_url_raw( $this->str( $input['webhook_url'] ?? '' ) );
		$allowedMethods                 = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );
		$method                         = strtoupper( $this->str( $input['webhook_method'] ?? 'POST', 'POST' ) );
		$out['webhook_method']          = in_array( $method, $allowedMethods, true ) ? $method : 'POST';
		$out['webhook_headers']         = $this->sanitizeMultiline( $this->str( $input['webhook_headers'] ?? '' ) );
		$out['webhook_body']            = $this->sanitizeMultiline( $this->str( $input['webhook_body'] ?? '' ) );

		$out = array_merge( $out, $this->sanitizeScheduleFields( $input, $defaults ) );

		// Deliberately handled only here, not in sanitizeScheduleFields(), so
		// the schedule-only save path (used by editors) can never toggle
		// their own access back on.
		$out['schedule_editor_access_enabled'] = ! empty( $input['schedule_editor_access_enabled'] );

		$out['debug_log_enabled'] = ! empty( $input['debug_log_enabled'] );

		return $out;
	}

	/**
	 * Sanitizes only the schedule_* fields from $input and merges them into
	 * the currently stored settings, leaving every other (admin-only) field
	 * exactly as stored. Used by the separate, lower-privilege "スケジュール
	 * 公開" admin page so saving a schedule can never touch or leak the
	 * email/Cloudflare/webhook settings.
	 *
	 * @param mixed $input
	 */
	public function sanitizeScheduleOnly( $input ): array {
		$input = is_array( $input ) ? $input : array();

		return array_merge( $this->get(), $this->sanitizeScheduleFields( $input, self::defaults() ) );
	}

	private function sanitizeScheduleFields( array $input, array $defaults ): array {
		$mode = $input['schedule_mode'] ?? 'daily';

		return array(
			'schedule_enabled'  => ! empty( $input['schedule_enabled'] ),
			'schedule_mode'     => in_array( $mode, array( 'one_time', 'daily', 'weekly' ), true ) ? $mode : 'daily',
			'schedule_time'     => $this->sanitizeTime( $this->str( $input['schedule_time'] ?? $defaults['schedule_time'], $defaults['schedule_time'] ) ),
			'schedule_date'     => $this->sanitizeDate( $this->str( $input['schedule_date'] ?? '' ) ),
			'schedule_weekdays' => $this->sanitizeWeekdays( $input['schedule_weekdays'] ?? array() ),
		);
	}

	/**
	 * Coerces a raw POST value to a string, falling back to $fallback for
	 * anything that isn't scalar (e.g. an array from a malformed submission,
	 * which would otherwise fatal the strictly-typed sanitizers below).
	 *
	 * @param mixed $value
	 */
	private function str( $value, string $fallback = '' ): string {
		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	private function sanitizeTime( string $raw ): string {
		if ( preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $raw ) ) {
			return $raw;
		}

		return self::defaults()['schedule_time'];
	}

	private function sanitizeDate( string $raw ): string {
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			return $raw;
		}

		return '';
	}

	private function sanitizeWeekdays( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$valid = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );

		return array_values( array_intersect( $valid, array_map( 'sanitize_key', $raw ) ) );
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
