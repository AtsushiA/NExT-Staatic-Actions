<?php
/**
 * Plugin Name: NExT Staatic Actions
 * Description: Staatic の公開（静的ジェネレート）完了時にメール通知・Cloudflareキャッシュパージ・Webhook通知を実行し、スケジュールに沿って自動公開も行います。
 * Version: 1.4.0
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Author: NExT-Season
 * Author URI: https://next-season.net/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: next-staatic-actions
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'NEXT_STAATIC_ACTIONS_VERSION', '1.4.0' );
define( 'NEXT_STAATIC_ACTIONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'NEXT_STAATIC_ACTIONS_URL', plugin_dir_url( __FILE__ ) );
define( 'NEXT_STAATIC_ACTIONS_SETTINGS_OPTION', 'next_staatic_actions_settings' );

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'NExT\\StaaticActions\\';
		if ( strpos( $class_name, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = NEXT_STAATIC_ACTIONS_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'STAATIC_VERSION' ) ) {
			add_action(
				'admin_notices',
				function () {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'NExT Staatic Actions には Staatic プラグインの有効化が必要です。', 'next-staatic-actions' )
					);
				}
			);

			return;
		}

		( new NExT\StaaticActions\Plugin() )->registerHooks();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( NExT\StaaticActions\Schedule\PublishScheduler::HOOK );
	}
);
