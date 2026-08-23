<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Tests\Integration;

use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Detection\NotificationGuard;
use NExT\StaaticActions\Detection\SuccessDetector;
use NExT\StaaticActions\Logging\DebugLogger;
use Staatic\Framework\Build;
use Staatic\Framework\Deployment;
use Staatic\WordPress\Publication\Publication;
use Staatic\WordPress\Publication\Task\FinishTask;
use Staatic\WordPress\Publication\Task\SetupTask;
use WP_UnitTestCase;

final class SuccessDetectorTest extends WP_UnitTestCase {

	private SuccessDetector $detector;

	public function set_up(): void {
		parent::set_up();
		$this->detector = new SuccessDetector( new NotificationGuard(), new DebugLogger( new Settings() ) );
	}

	private function make_publication( string $id, bool $finished ): Publication {
		$publication = new Publication( $id, new \DateTimeImmutable(), new Build(), new Deployment(), false, 1 );
		if ( $finished ) {
			$publication->markFinished();
		}

		return $publication;
	}

	public function test_fires_the_succeeded_hook_for_a_finished_publication_on_the_finish_task(): void {
		$fired = array();
		add_action(
			'next_staatic_actions_publish_succeeded',
			function ( array $context ) use ( &$fired ) {
				$fired[] = $context;
			}
		);

		$this->detector->onTaskAfter( $this->make_publication( 'pub-1', true ), new FinishTask() );

		self::assertCount( 1, $fired );
		self::assertSame( 'pub-1', $fired[0]['publication_id'] );
		self::assertSame( 'finished', $fired[0]['status'] );
	}

	public function test_does_not_fire_for_a_non_finish_task(): void {
		$fired = false;
		add_action(
			'next_staatic_actions_publish_succeeded',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->detector->onTaskAfter( $this->make_publication( 'pub-1', true ), new SetupTask() );

		self::assertFalse( $fired );
	}

	public function test_does_not_fire_when_status_is_not_finished(): void {
		$fired = false;
		add_action(
			'next_staatic_actions_publish_succeeded',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->detector->onTaskAfter( $this->make_publication( 'pub-1', false ), new FinishTask() );

		self::assertFalse( $fired );
	}

	public function test_does_not_fire_twice_for_the_same_publication(): void {
		$callCount = 0;
		add_action(
			'next_staatic_actions_publish_succeeded',
			function () use ( &$callCount ) {
				$callCount++;
			}
		);

		$publication = $this->make_publication( 'pub-1', true );
		$this->detector->onTaskAfter( $publication, new FinishTask() );
		$this->detector->onTaskAfter( $publication, new FinishTask() );

		self::assertSame( 1, $callCount );
	}
}
