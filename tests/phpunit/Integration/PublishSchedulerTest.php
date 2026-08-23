<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Tests\Integration;

use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Logging\DebugLogger;
use NExT\StaaticActions\Schedule\PublishScheduler;
use WP_UnitTestCase;

final class PublishSchedulerTest extends WP_UnitTestCase {

	private PublishScheduler $scheduler;

	public function set_up(): void {
		parent::set_up();
		$this->scheduler = new PublishScheduler( new Settings(), new DebugLogger( new Settings() ) );
		wp_clear_scheduled_hook( PublishScheduler::HOOK );
	}

	public function tear_down(): void {
		wp_clear_scheduled_hook( PublishScheduler::HOOK );
		parent::tear_down();
	}

	public function test_reschedule_does_nothing_when_disabled(): void {
		$this->scheduler->reschedule(
			array_merge(
				Settings::defaults(),
				array(
					'schedule_enabled' => false,
				)
			)
		);

		self::assertFalse( wp_next_scheduled( PublishScheduler::HOOK ) );
	}

	public function test_reschedule_schedules_a_recurring_daily_event(): void {
		$this->scheduler->reschedule(
			array_merge(
				Settings::defaults(),
				array(
					'schedule_enabled' => true,
					'schedule_mode'    => 'daily',
					'schedule_time'    => '03:00',
				)
			)
		);

		$next = wp_next_scheduled( PublishScheduler::HOOK );
		self::assertNotFalse( $next );
		self::assertSame( 'daily', wp_get_schedule( PublishScheduler::HOOK ) );
	}

	public function test_reschedule_skips_a_one_time_date_in_the_past(): void {
		$this->scheduler->reschedule(
			array_merge(
				Settings::defaults(),
				array(
					'schedule_enabled' => true,
					'schedule_mode'    => 'one_time',
					'schedule_date'    => '2000-01-01',
					'schedule_time'    => '03:00',
				)
			)
		);

		self::assertFalse( wp_next_scheduled( PublishScheduler::HOOK ) );
	}

	public function test_reschedule_schedules_a_future_one_time_event(): void {
		$futureDate = gmdate( 'Y-m-d', strtotime( '+1 year' ) );

		$this->scheduler->reschedule(
			array_merge(
				Settings::defaults(),
				array(
					'schedule_enabled' => true,
					'schedule_mode'    => 'one_time',
					'schedule_date'    => $futureDate,
					'schedule_time'    => '03:00',
				)
			)
		);

		self::assertNotFalse( wp_next_scheduled( PublishScheduler::HOOK ) );
	}

	public function test_run_scheduled_publish_fires_staatic_publish_hook(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'schedule_enabled' => true,
					'schedule_mode'    => 'daily',
				)
			)
		);

		$fired = false;
		add_action(
			'staatic_publish',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->scheduler->runScheduledPublish();

		self::assertTrue( $fired );
	}

	public function test_run_scheduled_publish_skips_a_non_matching_weekday(): void {
		$today    = strtolower( ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'D' ) );
		$otherDay = $today === 'mon' ? 'tue' : 'mon';

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'schedule_enabled'  => true,
					'schedule_mode'     => 'weekly',
					'schedule_weekdays' => array( $otherDay ),
				)
			)
		);

		$fired = false;
		add_action(
			'staatic_publish',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->scheduler->runScheduledPublish();

		self::assertFalse( $fired );
	}

	public function test_run_scheduled_publish_fires_on_a_matching_weekday(): void {
		$today = strtolower( ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'D' ) );

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'schedule_enabled'  => true,
					'schedule_mode'     => 'weekly',
					'schedule_weekdays' => array( $today ),
				)
			)
		);

		$fired = false;
		add_action(
			'staatic_publish',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->scheduler->runScheduledPublish();

		self::assertTrue( $fired );
	}

	public function test_run_scheduled_publish_does_nothing_when_disabled(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'schedule_enabled' => false,
				)
			)
		);

		$fired = false;
		add_action(
			'staatic_publish',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->scheduler->runScheduledPublish();

		self::assertFalse( $fired );
	}
}
