<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Tests\Integration;

use NExT\StaaticActions\Action\WebhookAction;
use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Logging\DebugLogger;
use WP_UnitTestCase;

final class WebhookActionTest extends WP_UnitTestCase {

	private WebhookAction $action;

	/** @var array<int, array{url: string, args: array}> */
	private array $requests = array();

	public function set_up(): void {
		parent::set_up();
		$this->action   = new WebhookAction( new Settings(), new DebugLogger( new Settings() ) );
		$this->requests = array();
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	private function interceptWith( array $response ): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $response ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $args,
				);

				return $response;
			},
			10,
			3
		);
	}

	public function test_send_test_reports_failure_when_url_is_empty(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'webhook_url' => '',
				)
			)
		);

		$result = $this->action->sendTest();

		self::assertFalse( $result['success'] );
		self::assertStringContainsString( 'not configured', $result['message'] );
	}

	public function test_send_test_reports_success_for_a_2xx_response(): void {
		$this->interceptWith(
			array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => '{"ok":true}',
			)
		);
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'webhook_url'    => 'https://example.com/hook',
					'webhook_method' => 'POST',
				)
			)
		);

		$result = $this->action->sendTest();

		self::assertTrue( $result['success'] );
		self::assertCount( 1, $this->requests );
		self::assertSame( 'https://example.com/hook', $this->requests[0]['url'] );
		self::assertSame( 'POST', $this->requests[0]['args']['method'] );
	}

	public function test_send_test_reports_failure_for_a_non_2xx_response(): void {
		$this->interceptWith(
			array(
				'response' => array(
					'code'    => 500,
					'message' => 'Internal Server Error',
				),
				'body'     => 'oops',
			)
		);
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'webhook_url' => 'https://example.com/hook',
				)
			)
		);

		$result = $this->action->sendTest();

		self::assertFalse( $result['success'] );
		self::assertStringContainsString( 'status 500', $result['message'] );
	}

	public function test_send_test_reports_failure_for_a_wp_error(): void {
		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( 'http_request_failed', 'Connection timed out' );
			}
		);
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'webhook_url' => 'https://example.invalid/hook',
				)
			)
		);

		$result = $this->action->sendTest();

		self::assertFalse( $result['success'] );
		self::assertStringContainsString( 'Connection timed out', $result['message'] );
	}

	public function test_send_test_defaults_to_json_body_with_context(): void {
		$this->interceptWith(
			array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => '',
			)
		);
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'webhook_url'  => 'https://example.com/hook',
					'webhook_body' => '',
				)
			)
		);

		$this->action->sendTest();

		$decoded = json_decode( $this->requests[0]['args']['body'], true );
		self::assertSame( 'test', $decoded['status'] );
		self::assertSame( 'application/json', $this->requests[0]['args']['headers']['Content-Type'] );
	}
}
