<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Tests\Integration;

use NExT\StaaticActions\Admin\SchedulePage;
use NExT\StaaticActions\Admin\Settings;
use WP_UnitTestCase;

final class SchedulePageTest extends WP_UnitTestCase {

	private SchedulePage $page;

	public function set_up(): void {
		parent::set_up();
		$this->page = new SchedulePage( new Settings() );
	}

	public function tear_down(): void {
		$administrator = get_role( 'administrator' );
		$administrator->remove_cap( SchedulePage::CAPABILITY );
		$editor = get_role( 'editor' );
		$editor->remove_cap( SchedulePage::CAPABILITY );
		parent::tear_down();
	}

	public function test_sync_grants_the_capability_to_editor_and_administrator_by_default(): void {
		self::assertFalse( get_role( 'editor' )->has_cap( SchedulePage::CAPABILITY ) );
		self::assertFalse( get_role( 'administrator' )->has_cap( SchedulePage::CAPABILITY ) );

		$this->page->syncEditorCapability();

		self::assertTrue( get_role( 'editor' )->has_cap( SchedulePage::CAPABILITY ) );
		self::assertTrue( get_role( 'administrator' )->has_cap( SchedulePage::CAPABILITY ) );
	}

	public function test_sync_does_not_grant_it_to_lower_roles(): void {
		$this->page->syncEditorCapability();

		self::assertFalse( get_role( 'author' )->has_cap( SchedulePage::CAPABILITY ) );
		self::assertFalse( get_role( 'contributor' )->has_cap( SchedulePage::CAPABILITY ) );
		self::assertFalse( get_role( 'subscriber' )->has_cap( SchedulePage::CAPABILITY ) );
	}

	public function test_an_editor_user_has_the_capability_after_granting(): void {
		$this->page->syncEditorCapability();

		$editorId = self::factory()->user->create( array( 'role' => 'editor' ) );
		$editor   = get_userdata( $editorId );

		self::assertTrue( $editor->has_cap( SchedulePage::CAPABILITY ) );
		self::assertFalse( $editor->has_cap( 'manage_options' ) );
	}

	public function test_sync_revokes_the_capability_from_editor_when_the_toggle_is_off(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'schedule_editor_access_enabled' => true,
				)
			)
		);
		$this->page->syncEditorCapability();
		self::assertTrue( get_role( 'editor' )->has_cap( SchedulePage::CAPABILITY ) );

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'schedule_editor_access_enabled' => false,
				)
			)
		);
		$this->page->syncEditorCapability();

		self::assertFalse( get_role( 'editor' )->has_cap( SchedulePage::CAPABILITY ) );
	}

	public function test_sync_keeps_the_administrator_capability_even_when_the_toggle_is_off(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'schedule_editor_access_enabled' => false,
				)
			)
		);

		$this->page->syncEditorCapability();

		self::assertTrue( get_role( 'administrator' )->has_cap( SchedulePage::CAPABILITY ) );
		self::assertFalse( get_role( 'editor' )->has_cap( SchedulePage::CAPABILITY ) );
	}

	public function test_sync_regrants_the_capability_once_the_toggle_is_turned_back_on(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'schedule_editor_access_enabled' => false,
				)
			)
		);
		$this->page->syncEditorCapability();
		self::assertFalse( get_role( 'editor' )->has_cap( SchedulePage::CAPABILITY ) );

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'schedule_editor_access_enabled' => true,
				)
			)
		);
		$this->page->syncEditorCapability();

		self::assertTrue( get_role( 'editor' )->has_cap( SchedulePage::CAPABILITY ) );
	}
}
