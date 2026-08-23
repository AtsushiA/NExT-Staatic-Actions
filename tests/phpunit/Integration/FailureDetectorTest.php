<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Tests\Integration;

use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Detection\FailureDetector;
use NExT\StaaticActions\Detection\NotificationGuard;
use NExT\StaaticActions\Logging\DebugLogger;
use Staatic\Framework\Build;
use Staatic\Framework\Deployment;
use Staatic\WordPress\Publication\Publication;
use WP_UnitTestCase;

final class FailureDetectorTest extends WP_UnitTestCase {

	private FailureDetector $detector;

	public function set_up(): void {
		parent::set_up();
		$this->detector = new FailureDetector( new NotificationGuard(), new DebugLogger( new Settings() ) );
	}

	private function make_publication( string $id ): Publication {
		return new Publication( $id, new \DateTimeImmutable(), new Build(), new Deployment(), false, 1 );
	}

	public function test_fires_the_failed_hook_once_the_publication_is_marked_failed(): void {
		$fired = array();
		add_action(
			'next_staatic_actions_publish_failed',
			function ( array $context ) use ( &$fired ) {
				$fired[] = $context;
			}
		);

		$publication = $this->make_publication( 'pub-1' );
		$this->detector->captureReference( $publication );
		$publication->markFailed();
		$this->detector->checkForFailure();

		self::assertCount( 1, $fired );
		self::assertSame( 'pub-1', $fired[0]['publication_id'] );
		self::assertSame( 'failed', $fired[0]['status'] );
		self::assertNotSame( '', $fired[0]['failure_message'] );
	}

	public function test_does_not_fire_while_still_in_progress(): void {
		$fired = false;
		add_action(
			'next_staatic_actions_publish_failed',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$publication = $this->make_publication( 'pub-1' );
		$this->detector->captureReference( $publication );
		$this->detector->checkForFailure();

		self::assertFalse( $fired );
	}

	public function test_does_not_fire_twice_for_the_same_publication(): void {
		$callCount = 0;
		add_action(
			'next_staatic_actions_publish_failed',
			function () use ( &$callCount ) {
				$callCount++;
			}
		);

		$publication = $this->make_publication( 'pub-1' );
		$this->detector->captureReference( $publication );
		$publication->markFailed();
		$this->detector->checkForFailure();
		$this->detector->checkForFailure();

		self::assertSame( 1, $callCount );
	}

	public function test_does_nothing_when_no_reference_was_captured(): void {
		$fired = false;
		add_action(
			'next_staatic_actions_publish_failed',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->detector->checkForFailure();

		self::assertFalse( $fired );
	}
}
