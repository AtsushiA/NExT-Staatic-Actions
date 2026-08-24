<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Tests\Integration;

use NExT\StaaticActions\Admin\SchedulePage;
use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Schedule\PublishScheduler;
use WP_UnitTestCase;

final class UninstallTest extends WP_UnitTestCase {

	public function tear_down(): void {
		$administrator = get_role( 'administrator' );
		$administrator->remove_cap( SchedulePage::CAPABILITY );
		$editor = get_role( 'editor' );
		$editor->remove_cap( SchedulePage::CAPABILITY );
		parent::tear_down();
	}

	public function test_uninstall_deletes_the_option_clears_the_schedule_and_revokes_the_capability(): void {
		update_option( Settings::OPTION_NAME, Settings::defaults() );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', PublishScheduler::HOOK );
		get_role( 'administrator' )->add_cap( SchedulePage::CAPABILITY );
		get_role( 'editor' )->add_cap( SchedulePage::CAPABILITY );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}
		require dirname( __DIR__, 3 ) . '/uninstall.php';

		self::assertFalse( get_option( Settings::OPTION_NAME ) );
		self::assertFalse( wp_next_scheduled( PublishScheduler::HOOK ) );
		self::assertFalse( get_role( 'administrator' )->has_cap( SchedulePage::CAPABILITY ) );
		self::assertFalse( get_role( 'editor' )->has_cap( SchedulePage::CAPABILITY ) );
	}
}
