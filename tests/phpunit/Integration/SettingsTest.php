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

		// Every checkbox-style field, schedule_editor_access_enabled
		// included, follows the same "absent from input means unchecked"
		// convention as a real browser form submission would, even though
		// its own default() value (used only to pre-fill Settings::get()
		// for a brand new install) is true.
		self::assertSame(
			array_merge( Settings::defaults(), array( 'schedule_editor_access_enabled' => false ) ),
			$result
		);
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

	public function test_sanitize_schedule_only_preserves_other_settings(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'email_enabled_success' => true,
					'email_recipients'      => 'existing@example.com',
					'cloudflare_zone_id'    => 'existing-zone',
					'cloudflare_api_token'  => 'existing-secret-token',
					'webhook_url'           => 'https://existing.example.com/hook',
				)
			)
		);

		$result = $this->settings->sanitizeScheduleOnly(
			array(
				'schedule_enabled' => '1',
				'schedule_mode'    => 'daily',
				'schedule_time'    => '14:30',
			)
		);

		// The fields the schedule-only page never displays or submits must
		// come through completely untouched from what was already stored.
		self::assertTrue( $result['email_enabled_success'] );
		self::assertSame( 'existing@example.com', $result['email_recipients'] );
		self::assertSame( 'existing-zone', $result['cloudflare_zone_id'] );
		self::assertSame( 'existing-secret-token', $result['cloudflare_api_token'] );
		self::assertSame( 'https://existing.example.com/hook', $result['webhook_url'] );

		// The submitted schedule fields are applied and sanitized as usual.
		self::assertTrue( $result['schedule_enabled'] );
		self::assertSame( 'daily', $result['schedule_mode'] );
		self::assertSame( '14:30', $result['schedule_time'] );
	}

	public function test_sanitize_schedule_only_does_not_fatal_on_non_array_input(): void {
		$result = $this->settings->sanitizeScheduleOnly( 'not-an-array' );

		self::assertSame( Settings::defaults()['schedule_mode'], $result['schedule_mode'] );
	}

	public function test_sanitize_schedule_only_rejects_an_invalid_time_as_an_array(): void {
		$result = $this->settings->sanitizeScheduleOnly(
			array(
				'schedule_time' => array( 'unexpected' ),
			)
		);

		self::assertIsString( $result['schedule_time'] );
	}

	public function test_sanitize_schedule_only_cannot_toggle_its_own_editor_access(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'schedule_editor_access_enabled' => false,
				)
			)
		);

		// An editor's own save request has no way to flip this flag, since
		// the schedule-only page never renders or submits this field.
		$result = $this->settings->sanitizeScheduleOnly(
			array(
				'schedule_editor_access_enabled' => '1',
			)
		);

		self::assertFalse( $result['schedule_editor_access_enabled'] );
	}

	public function test_sanitize_casts_the_editor_access_toggle_to_a_boolean(): void {
		$result = $this->settings->sanitize(
			array(
				'schedule_editor_access_enabled' => '1',
			)
		);
		self::assertTrue( $result['schedule_editor_access_enabled'] );

		$result = $this->settings->sanitize( array() );
		self::assertFalse( $result['schedule_editor_access_enabled'] );
	}
}
