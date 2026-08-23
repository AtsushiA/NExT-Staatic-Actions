<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

delete_option( 'next_staatic_actions_settings' );
wp_clear_scheduled_hook( 'next_staatic_actions_scheduled_publish' );
