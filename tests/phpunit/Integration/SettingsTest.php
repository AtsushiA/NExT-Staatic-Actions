<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Tests\Integration;

use NExT\StaaticActions\Admin\Settings;
use WP_UnitTestCase;

final class SettingsTest extends WP_UnitTestCase {

	private Settings $settings;

	public function set_up(): void {
		parent::set_up();
		$this->settings = new Settings();
	}

	public function test_get_returns_defaults_when_option_is_unset(): void {
		self::assertSame( Settings::defaults(), $this->settings->get() );
	}

	public function test_sanitize_returns_defaults_for_non_array_input(): void {
		// WordPress core passes a raw (non-array) value here when the
		// submitted form field was not an array; see wp-admin/options.php.
		$result = $this->settings->sanitize( 'not-an-array' );

		self::assertSame( Settings::defaults(), $result );
	}

	public function test_sanitize_filters_invalid_email_addresses(): void {
		$result = $this->settings->sanitize(
			array(
				'email_recipients' => 'valid@example.com, not-an-email, second@example.com',
			)
		);

		self::assertSame( 'valid@example.com, second@example.com', $result['email_recipients'] );
	}

	public function test_sanitize_strips_dangerous_url_schemes(): void {
		$result = $this->settings->sanitize(
			array(
				'webhook_url' => 'javascript:alert(1)',
			)
		);

		self::assertSame( '', $result['webhook_url'] );
	}

	public function test_sanitize_falls_back_to_post_for_disallowed_method(): void {
		$result = $this->settings->sanitize(
			array(
				'webhook_method' => 'TRACE',
			)
		);

		self::assertSame( 'POST', $result['webhook_method'] );
	}

	public function test_sanitize_accepts_allowed_method(): void {
		$result = $this->settings->sanitize(
			array(
				'webhook_method' => 'put',
			)
		);

		self::assertSame( 'PUT', $result['webhook_method'] );
	}

	public function test_sanitize_casts_checkboxes_to_booleans(): void {
		$result = $this->settings->sanitize(
			array(
				'email_enabled_success' => '1',
			)
		);

		self::assertTrue( $result['email_enabled_success'] );
		self::assertFalse( $result['email_enabled_failure'] );
	}

	public function test_sanitize_rejects_an_invalid_schedule_time(): void {
		$result = $this->settings->sanitize(
			array(
				'schedule_time' => 'not-a-time',
			)
		);

		self::assertSame( Settings::defaults()['schedule_time'], $result['schedule_time'] );
	}

	public function test_sanitize_accepts_a_valid_schedule_time(): void {
		$result = $this->settings->sanitize(
			array(
				'schedule_time' => '23:45',
			)
		);

		self::assertSame( '23:45', $result['schedule_time'] );
	}

	public function test_sanitize_rejects_an_invalid_schedule_mode(): void {
		$result = $this->settings->sanitize(
			array(
				'schedule_mode' => 'hourly',
			)
		);

		self::assertSame( 'daily', $result['schedule_mode'] );
	}

	public function test_sanitize_rejects_a_malformed_schedule_date(): void {
		$result = $this->settings->sanitize(
			array(
				'schedule_date' => 'tomorrow',
			)
		);

		self::assertSame( '', $result['schedule_date'] );
	}

	public function test_sanitize_filters_invalid_weekdays(): void {
		$result = $this->settings->sanitize(
			array(
				'schedule_weekdays' => array( 'mon', 'funday', 'fri' ),
			)
		);

		self::assertSame( array( 'mon', 'fri' ), $result['schedule_weekdays'] );
	}

	/**
	 * A malformed submission (e.g. a duplicated field name posted as
	 * `field[]=x`) can put an array where a scalar string is expected for
	 * any of these fields; sanitize() must fall back to a safe default
	 * instead of fataling with a TypeError.
	 *
	 * @dataProvider provideStringFieldsRejectArrayInput
	 */
	public function test_sanitize_does_not_fatal_when_a_string_field_is_posted_as_an_array( string $field ): void {
		$result = $this->settings->sanitize(
			array(
				$field => array( 'unexpected', 'array' ),
			)
		);

		self::assertIsString( $result[ $field ] );
	}

	public function provideStringFieldsRejectArrayInput(): array {
		return array(
			array( 'email_recipients' ),
			array( 'email_subject' ),
			array( 'email_body' ),
			array( 'cloudflare_zone_id' ),
			array( 'cloudflare_api_token' ),
			array( 'webhook_url' ),
			array( 'webhook_method' ),
			array( 'webhook_headers' ),
			array( 'webhook_body' ),
			array( 'schedule_time' ),
			array( 'schedule_date' ),
		);
	}
}
