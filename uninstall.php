<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

delete_option( 'next_staatic_actions_settings' );
wp_clear_scheduled_hook( 'next_staatic_actions_scheduled_publish' );

// Custom capability granted to administrator (always) and editor (if the
// "編集者にスケジュール公開の操作を許可" toggle was enabled) by
// SchedulePage::syncEditorCapability(); nothing else checks it, but it
// should not linger in the roles option once the plugin is gone.
foreach ( array( 'administrator', 'editor' ) as $roleName ) {
	$roleObject = get_role( $roleName );
	if ( $roleObject ) {
		$roleObject->remove_cap( 'next_staatic_actions_manage_schedule' );
	}
}
