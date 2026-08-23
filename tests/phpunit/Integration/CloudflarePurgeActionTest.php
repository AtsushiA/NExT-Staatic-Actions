<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Tests\Integration;

use NExT\StaaticActions\Action\CloudflarePurgeAction;
use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Logging\DebugLogger;
use WP_UnitTestCase;

final class CloudflarePurgeActionTest extends WP_UnitTestCase {

	private CloudflarePurgeAction $action;

	/** @var array<int, array{url: string, args: array}> */
	private array $requests = array();

	public function set_up(): void {
		parent::set_up();
		$this->action   = new CloudflarePurgeAction( new Settings(), new DebugLogger( new Settings() ) );
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

	private function configure( array $overrides = array() ): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array_merge(
					array(
						'cloudflare_zone_id'   => 'zone123',
						'cloudflare_api_token' => 'token456',
					),
					$overrides
				)
			)
		);
	}

	public function test_verify_connection_reports_failure_when_not_configured(): void {
		$this->configure(
			array(
				'cloudflare_zone_id'   => '',
				'cloudflare_api_token' => '',
			)
		);

		$result = $this->action->verifyConnection();

		self::assertFalse( $result['success'] );
	}

	public function test_verify_connection_reports_success_and_includes_zone_name(): void {
		$this->interceptWith(
			array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => wp_json_encode(
					array(
						'success' => true,
						'result'  => array( 'name' => 'example.com' ),
					)
				),
			)
		);
		$this->configure();

		$result = $this->action->verifyConnection();

		self::assertTrue( $result['success'] );
		self::assertStringContainsString( 'example.com', $result['message'] );
		self::assertSame( 'GET', $this->requests[0]['args']['method'] );
		self::assertSame( 'Bearer token456', $this->requests[0]['args']['headers']['Authorization'] );
		self::assertStringContainsString( '/zones/zone123', $this->requests[0]['url'] );
	}

	public function test_verify_connection_surfaces_the_cloudflare_error_message(): void {
		$this->interceptWith(
			array(
				'response' => array(
					'code'    => 403,
					'message' => 'Forbidden',
				),
				'body'     => wp_json_encode(
					array(
						'success' => false,
						'errors'  => array(
							array(
								'code'    => 9109,
								'message' => 'Invalid API Token',
							),
						),
					)
				),
			)
		);
		$this->configure();

		$result = $this->action->verifyConnection();

		self::assertFalse( $result['success'] );
		self::assertStringContainsString( 'Invalid API Token', $result['message'] );
	}

	public function test_purge_now_reports_success_for_a_successful_purge(): void {
		$this->interceptWith(
			array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => wp_json_encode( array( 'success' => true ) ),
			)
		);
		$this->configure();

		$result = $this->action->purgeNow();

		self::assertTrue( $result['success'] );
		$body = json_decode( $this->requests[0]['args']['body'], true );
		self::assertTrue( $body['purge_everything'] );
	}

	public function test_purge_now_surfaces_the_cloudflare_error_message(): void {
		$this->interceptWith(
			array(
				'response' => array(
					'code'    => 400,
					'message' => 'Bad Request',
				),
				'body'     => wp_json_encode(
					array(
						'success' => false,
						'errors'  => array(
							array(
								'code'    => 1000,
								'message' => 'Zone not found',
							),
						),
					)
				),
			)
		);
		$this->configure();

		$result = $this->action->purgeNow();

		self::assertFalse( $result['success'] );
		self::assertStringContainsString( 'Zone not found', $result['message'] );
	}

	public function test_purge_now_reports_failure_when_not_configured(): void {
		$this->configure( array( 'cloudflare_zone_id' => '' ) );

		$result = $this->action->purgeNow();

		self::assertFalse( $result['success'] );
		self::assertEmpty( $this->requests );
	}
}
