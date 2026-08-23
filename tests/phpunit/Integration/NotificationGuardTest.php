<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Tests\Integration;

use NExT\StaaticActions\Detection\NotificationGuard;
use WP_UnitTestCase;

final class NotificationGuardTest extends WP_UnitTestCase {

	private NotificationGuard $guard;

	public function set_up(): void {
		parent::set_up();
		$this->guard = new NotificationGuard();
	}

	public function test_allows_the_first_notification(): void {
		self::assertTrue( $this->guard->shouldNotify( 'pub-1', 'succeeded' ) );
	}

	public function test_blocks_a_duplicate_notification_for_the_same_status(): void {
		$this->guard->shouldNotify( 'pub-1', 'succeeded' );

		self::assertFalse( $this->guard->shouldNotify( 'pub-1', 'succeeded' ) );
	}

	public function test_allows_a_different_status_for_the_same_publication(): void {
		$this->guard->shouldNotify( 'pub-1', 'succeeded' );

		self::assertTrue( $this->guard->shouldNotify( 'pub-1', 'failed' ) );
	}

	public function test_allows_the_same_status_for_a_different_publication(): void {
		$this->guard->shouldNotify( 'pub-1', 'succeeded' );

		self::assertTrue( $this->guard->shouldNotify( 'pub-2', 'succeeded' ) );
	}
}
