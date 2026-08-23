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
}
