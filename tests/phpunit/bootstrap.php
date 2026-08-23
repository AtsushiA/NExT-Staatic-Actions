<?php
/**
 * PHPUnit bootstrap file for Integration tests.
 */

// Composer autoloader.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// WordPress test library.
require getenv( 'WP_TESTS_DIR' ) . '/includes/functions.php';

/**
 * Load the Staatic stand-ins and the plugin itself.
 */
function _next_staatic_actions_manually_load_plugin() {
	require dirname( __DIR__, 2 ) . '/tests/phpunit/stubs/staatic-stubs.php';
	require dirname( __DIR__, 2 ) . '/next-staatic-actions.php';
}
tests_add_filter( 'muplugins_loaded', '_next_staatic_actions_manually_load_plugin' );

// Boot WordPress' test environment.
require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';
