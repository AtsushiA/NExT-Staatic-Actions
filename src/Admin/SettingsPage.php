<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Admin;

use NExT\StaaticActions\Action\CloudflarePurgeAction;
use NExT\StaaticActions\Action\EmailAction;
use NExT\StaaticActions\Action\WebhookAction;
use NExT\StaaticActions\Logging\DebugLogger;

final class SettingsPage {

	private const PAGE_SLUG    = 'next-staatic-actions';
	private const OPTION_GROUP = 'next_staatic_actions_settings_group';

	private const TAB_IDS = array( 'nsa_email', 'nsa_cloudflare', 'nsa_webhook', 'nsa_schedule', 'nsa_advanced' );

	/** @var Settings */
	private $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function registerHooks(): void {
		// Priority 20: must run after Staatic's own admin_menu callback (default
		// priority 10) has registered its top-level 'staatic' page, otherwise
		// WordPress resolves a mismatched hookname for this submenu and denies access.
		add_action( 'admin_menu', array( $this, 'registerMenu' ), 20 );
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
		add_action( 'wp_ajax_next_staatic_actions_test_send', array( $this, 'ajaxTestSend' ) );
	}

	public function registerMenu(): void {
		$parentSlug = defined( 'STAATIC_VERSION' ) ? 'staatic' : null;

		if ( $parentSlug ) {
			add_submenu_page(
				$parentSlug,
				__( 'NExT Staatic Actions', 'next-staatic-actions' ),
				__( 'Actions', 'next-staatic-actions' ),
				'manage_options',
				self::PAGE_SLUG,
				array( $this, 'renderPage' )
			);

			return;
		}

		add_menu_page(
			__( 'NExT Staatic Actions', 'next-staatic-actions' ),
			__( 'Staatic Actions', 'next-staatic-actions' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'renderPage' )
		);
	}

	public function registerSettings(): void {
		register_setting(
			self::OPTION_GROUP,
			Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		add_settings_section( 'nsa_email', __( 'メール通知', 'next-staatic-actions' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'email_enabled_success', __( '成功時に送信', 'next-staatic-actions' ), array( $this, 'fieldCheckbox' ), self::PAGE_SLUG, 'nsa_email', array( 'key' => 'email_enabled_success' ) );
		add_settings_field( 'email_enabled_failure', __( '失敗時に送信', 'next-staatic-actions' ), array( $this, 'fieldCheckbox' ), self::PAGE_SLUG, 'nsa_email', array( 'key' => 'email_enabled_failure' ) );
		add_settings_field( 'email_recipients', __( '宛先（カンマ・改行区切りで複数可）', 'next-staatic-actions' ), array( $this, 'fieldTextarea' ), self::PAGE_SLUG, 'nsa_email', array( 'key' => 'email_recipients' ) );
		add_settings_field( 'email_subject', __( '件名', 'next-staatic-actions' ), array( $this, 'fieldText' ), self::PAGE_SLUG, 'nsa_email', array( 'key' => 'email_subject' ) );
		add_settings_field( 'email_body', __( '本文', 'next-staatic-actions' ), array( $this, 'fieldTextarea' ), self::PAGE_SLUG, 'nsa_email', array( 'key' => 'email_body' ) );
		add_settings_field( 'email_test_send', __( 'テスト送信', 'next-staatic-actions' ), array( $this, 'fieldTestSend' ), self::PAGE_SLUG, 'nsa_email', array( 'channel' => 'email' ) );

		add_settings_section( 'nsa_cloudflare', __( 'Cloudflare キャッシュパージ', 'next-staatic-actions' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'cloudflare_enabled_success', __( '成功時にパージ', 'next-staatic-actions' ), array( $this, 'fieldCheckbox' ), self::PAGE_SLUG, 'nsa_cloudflare', array( 'key' => 'cloudflare_enabled_success' ) );
		add_settings_field( 'cloudflare_enabled_failure', __( '失敗時にパージ', 'next-staatic-actions' ), array( $this, 'fieldCheckbox' ), self::PAGE_SLUG, 'nsa_cloudflare', array( 'key' => 'cloudflare_enabled_failure' ) );
		add_settings_field( 'cloudflare_zone_id', __( 'Zone ID', 'next-staatic-actions' ), array( $this, 'fieldText' ), self::PAGE_SLUG, 'nsa_cloudflare', array( 'key' => 'cloudflare_zone_id' ) );
		add_settings_field( 'cloudflare_api_token', __( 'API トークン', 'next-staatic-actions' ), array( $this, 'fieldPassword' ), self::PAGE_SLUG, 'nsa_cloudflare', array( 'key' => 'cloudflare_api_token' ) );
		add_settings_field(
			'cloudflare_verify',
			__( '接続確認', 'next-staatic-actions' ),
			array( $this, 'fieldTestSend' ),
			self::PAGE_SLUG,
			'nsa_cloudflare',
			array(
				'channel' => 'cloudflare_verify',
				'label'   => __( 'Zone ID・APIトークンを確認', 'next-staatic-actions' ),
			)
		);
		add_settings_field(
			'cloudflare_purge_now',
			__( '今すぐパージ', 'next-staatic-actions' ),
			array( $this, 'fieldTestSend' ),
			self::PAGE_SLUG,
			'nsa_cloudflare',
			array(
				'channel' => 'cloudflare_purge',
				'label'   => __( '今すぐキャッシュをパージ', 'next-staatic-actions' ),
				'confirm' => __( 'Cloudflare のキャッシュを今すぐ全て削除します。よろしいですか？', 'next-staatic-actions' ),
			)
		);

		add_settings_section( 'nsa_webhook', __( 'Webhook 通知', 'next-staatic-actions' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'webhook_enabled_success', __( '成功時に送信', 'next-staatic-actions' ), array( $this, 'fieldCheckbox' ), self::PAGE_SLUG, 'nsa_webhook', array( 'key' => 'webhook_enabled_success' ) );
		add_settings_field( 'webhook_enabled_failure', __( '失敗時に送信', 'next-staatic-actions' ), array( $this, 'fieldCheckbox' ), self::PAGE_SLUG, 'nsa_webhook', array( 'key' => 'webhook_enabled_failure' ) );
		add_settings_field( 'webhook_url', __( 'URL', 'next-staatic-actions' ), array( $this, 'fieldText' ), self::PAGE_SLUG, 'nsa_webhook', array( 'key' => 'webhook_url' ) );
		add_settings_field( 'webhook_method', __( 'HTTPメソッド', 'next-staatic-actions' ), array( $this, 'fieldMethodSelect' ), self::PAGE_SLUG, 'nsa_webhook', array( 'key' => 'webhook_method' ) );
		add_settings_field( 'webhook_headers', __( 'ヘッダー（1行につき Name: value）', 'next-staatic-actions' ), array( $this, 'fieldTextarea' ), self::PAGE_SLUG, 'nsa_webhook', array( 'key' => 'webhook_headers' ) );
		add_settings_field( 'webhook_body', __( 'ボディ（空欄の場合はJSONを自動送信）', 'next-staatic-actions' ), array( $this, 'fieldTextarea' ), self::PAGE_SLUG, 'nsa_webhook', array( 'key' => 'webhook_body' ) );
		add_settings_field( 'webhook_test_send', __( 'テスト送信', 'next-staatic-actions' ), array( $this, 'fieldTestSend' ), self::PAGE_SLUG, 'nsa_webhook', array( 'channel' => 'webhook' ) );

		add_settings_section( 'nsa_schedule', __( 'スケジュール公開', 'next-staatic-actions' ), array( $this, 'sectionScheduleIntro' ), self::PAGE_SLUG );
		add_settings_field( 'schedule_enabled', __( 'スケジュール公開を有効化', 'next-staatic-actions' ), array( $this, 'fieldCheckbox' ), self::PAGE_SLUG, 'nsa_schedule', array( 'key' => 'schedule_enabled' ) );
		add_settings_field( 'schedule_mode', __( '頻度', 'next-staatic-actions' ), array( $this, 'fieldModeSelect' ), self::PAGE_SLUG, 'nsa_schedule', array( 'key' => 'schedule_mode' ) );
		add_settings_field( 'schedule_date', __( '日付（頻度が「指定日時」の場合のみ）', 'next-staatic-actions' ), array( $this, 'fieldDate' ), self::PAGE_SLUG, 'nsa_schedule', array( 'key' => 'schedule_date' ) );
		add_settings_field( 'schedule_weekdays', __( '曜日（頻度が「毎週指定曜日」の場合のみ）', 'next-staatic-actions' ), array( $this, 'fieldWeekdays' ), self::PAGE_SLUG, 'nsa_schedule', array( 'key' => 'schedule_weekdays' ) );
		add_settings_field( 'schedule_time', __( '時刻', 'next-staatic-actions' ), array( $this, 'fieldTime' ), self::PAGE_SLUG, 'nsa_schedule', array( 'key' => 'schedule_time' ) );

		add_settings_section( 'nsa_advanced', __( '詳細設定', 'next-staatic-actions' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'debug_log_enabled', __( 'デバッグログを有効化', 'next-staatic-actions' ), array( $this, 'fieldCheckbox' ), self::PAGE_SLUG, 'nsa_advanced', array( 'key' => 'debug_log_enabled' ) );
	}

	public function renderPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab display, not a state-changing action; the value is checked against an allow-list below.
		$requestedTab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$activeTab    = in_array( $requestedTab, self::TAB_IDS, true ) ? $requestedTab : self::TAB_IDS[0];
		$labels       = $this->tabLabels();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NExT Staatic Actions', 'next-staatic-actions' ); ?></h1>
			<p><?php esc_html_e( 'プレースホルダー: {{status}} {{publication_id}} {{destination_url}} {{date_finished}} {{site_url}} {{admin_publication_url}} {{failure_message}}', 'next-staatic-actions' ); ?></p>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( self::TAB_IDS as $tabId ) : ?>
					<?php
					$url         = add_query_arg(
						array(
							'page' => self::PAGE_SLUG,
							'tab'  => $tabId,
						),
						admin_url( 'admin.php' )
					);
					$activeClass = $tabId === $activeTab ? 'nav-tab-active' : '';
					printf(
						'<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
						esc_url( $url ),
						esc_attr( $activeClass ),
						esc_html( $labels[ $tabId ] )
					);
					?>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				// All tabs' fields are rendered in every request (only display toggles
				// per tab) so the single shared option array is never partially
				// overwritten with defaults when saving from a non-active tab.
				foreach ( self::TAB_IDS as $tabId ) {
					$this->renderTabPanel( $tabId, $tabId === $activeTab );
				}
				submit_button();
				?>
			</form>
		</div>
		<script>
		( function () {
			document.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '.nsa-test-send' );
				if ( ! button ) {
					return;
				}
				event.preventDefault();

				if ( button.dataset.confirm && ! window.confirm( button.dataset.confirm ) ) {
					return;
				}

				var channel = button.dataset.channel;
				var result  = document.querySelector( '.nsa-test-result[data-channel="' + channel + '"]' );
				var data    = new FormData();
				data.append( 'action', 'next_staatic_actions_test_send' );
				data.append( 'nonce', button.dataset.nonce );
				data.append( 'channel', channel );

				button.disabled  = true;
				result.textContent = <?php echo wp_json_encode( __( '送信中…', 'next-staatic-actions' ) ); ?>;
				result.style.color = '';

				fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: data } )
					.then( function ( response ) { return response.json(); } )
					.then( function ( json ) {
						button.disabled     = false;
						result.textContent  = ( json.data && json.data.message ) ? json.data.message : '';
						result.style.color  = json.success ? '#008a20' : '#d63638';
					} )
					.catch( function () {
						button.disabled    = false;
						result.textContent = <?php echo wp_json_encode( __( '通信エラーが発生しました。', 'next-staatic-actions' ) ); ?>;
						result.style.color = '#d63638';
					} );
			} );
		} )();
		</script>
		<?php
	}

	private function tabLabels(): array {
		return array(
			'nsa_email'      => __( 'メール通知', 'next-staatic-actions' ),
			'nsa_cloudflare' => __( 'Cloudflare キャッシュパージ', 'next-staatic-actions' ),
			'nsa_webhook'    => __( 'Webhook 通知', 'next-staatic-actions' ),
			'nsa_schedule'   => __( 'スケジュール公開', 'next-staatic-actions' ),
			'nsa_advanced'   => __( '詳細設定', 'next-staatic-actions' ),
		);
	}

	private function renderTabPanel( string $sectionId, bool $isActive ): void {
		global $wp_settings_sections;

		printf(
			'<div class="nsa-tab-panel" data-tab="%s"%s>',
			esc_attr( $sectionId ),
			$isActive ? '' : ' style="display:none;"'
		);

		$section = $wp_settings_sections[ self::PAGE_SLUG ][ $sectionId ] ?? null;
		if ( $section && $section['callback'] ) {
			call_user_func( $section['callback'], $section );
		}

		echo '<table class="form-table" role="presentation">';
		do_settings_fields( self::PAGE_SLUG, $sectionId );
		echo '</table>';

		echo '</div>';
	}

	public function sectionScheduleIntro(): void {
		echo '<p>' . esc_html__( '指定した時刻に Staatic の公開（静的ジェネレート）を自動実行します。時刻はサイトのタイムゾーンで判定されます。', 'next-staatic-actions' ) . '</p>';
	}

	public function fieldModeSelect( array $args ): void {
		$value = $this->settings->get()[ $args['key'] ];
		$modes = array(
			'one_time' => __( '指定日時に1回だけ実行', 'next-staatic-actions' ),
			'daily'    => __( '毎日実行', 'next-staatic-actions' ),
			'weekly'   => __( '毎週指定曜日に実行', 'next-staatic-actions' ),
		);
		printf( '<select name="%1$s[%2$s]">', esc_attr( Settings::OPTION_NAME ), esc_attr( $args['key'] ) );
		foreach ( $modes as $mode => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $mode ),
				selected( $value, $mode, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	public function fieldTime( array $args ): void {
		$value = $this->settings->get()[ $args['key'] ];
		printf(
			'<input type="time" name="%1$s[%2$s]" value="%3$s" />',
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( $args['key'] ),
			esc_attr( $value )
		);
	}

	public function fieldDate( array $args ): void {
		$value = $this->settings->get()[ $args['key'] ];
		printf(
			'<input type="date" name="%1$s[%2$s]" value="%3$s" />',
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( $args['key'] ),
			esc_attr( $value )
		);
	}

	public function fieldWeekdays( array $args ): void {
		$value    = $this->settings->get()[ $args['key'] ];
		$weekdays = array(
			'mon' => __( '月', 'next-staatic-actions' ),
			'tue' => __( '火', 'next-staatic-actions' ),
			'wed' => __( '水', 'next-staatic-actions' ),
			'thu' => __( '木', 'next-staatic-actions' ),
			'fri' => __( '金', 'next-staatic-actions' ),
			'sat' => __( '土', 'next-staatic-actions' ),
			'sun' => __( '日', 'next-staatic-actions' ),
		);
		foreach ( $weekdays as $day => $label ) {
			printf(
				'<label style="margin-right:1em;"><input type="checkbox" name="%1$s[%2$s][]" value="%3$s" %4$s /> %5$s</label>',
				esc_attr( Settings::OPTION_NAME ),
				esc_attr( $args['key'] ),
				esc_attr( $day ),
				checked( in_array( $day, $value, true ), true, false ),
				esc_html( $label )
			);
		}
	}

	public function fieldCheckbox( array $args ): void {
		$value = $this->settings->get()[ $args['key'] ];
		printf(
			'<input type="checkbox" name="%1$s[%2$s]" value="1" %3$s />',
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( $args['key'] ),
			checked( $value, true, false )
		);
	}

	public function fieldText( array $args ): void {
		$value = $this->settings->get()[ $args['key'] ];
		printf(
			'<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" />',
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( $args['key'] ),
			esc_attr( $value )
		);
	}

	public function fieldPassword( array $args ): void {
		$value = $this->settings->get()[ $args['key'] ];
		printf(
			'<input type="password" class="regular-text" autocomplete="off" name="%1$s[%2$s]" value="%3$s" />',
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( $args['key'] ),
			esc_attr( $value )
		);
	}

	public function fieldTextarea( array $args ): void {
		$value = $this->settings->get()[ $args['key'] ];
		printf(
			'<textarea class="large-text" rows="4" name="%1$s[%2$s]">%3$s</textarea>',
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( $args['key'] ),
			esc_textarea( $value )
		);
	}

	public function fieldMethodSelect( array $args ): void {
		$value   = $this->settings->get()[ $args['key'] ];
		$methods = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );
		printf( '<select name="%1$s[%2$s]">', esc_attr( Settings::OPTION_NAME ), esc_attr( $args['key'] ) );
		foreach ( $methods as $method ) {
			printf(
				'<option value="%1$s" %2$s>%1$s</option>',
				esc_attr( $method ),
				selected( $value, $method, false )
			);
		}
		echo '</select>';
	}

	public function fieldTestSend( array $args ): void {
		$label       = $args['label'] ?? __( '保存済みの設定でテスト送信', 'next-staatic-actions' );
		$confirmAttr = isset( $args['confirm'] ) ? sprintf( ' data-confirm="%s"', esc_attr( $args['confirm'] ) ) : '';
		printf(
			'<button type="button" class="button nsa-test-send" data-channel="%1$s" data-nonce="%2$s"%4$s>%3$s</button> <span class="nsa-test-result" data-channel="%1$s"></span>',
			esc_attr( $args['channel'] ),
			esc_attr( wp_create_nonce( 'next_staatic_actions_test_send' ) ),
			esc_html( $label ),
			$confirmAttr // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already a fully-escaped ' data-confirm="..."' attribute string (or ''), built above via esc_attr().
		);
	}

	public function ajaxTestSend(): void {
		check_ajax_referer( 'next_staatic_actions_test_send', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'next-staatic-actions' ) ), 403 );

			return;
		}

		$channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$logger  = new DebugLogger( $this->settings );

		if ( $channel === 'email' ) {
			$result = ( new EmailAction( $this->settings, $logger ) )->sendTest();
		} elseif ( $channel === 'webhook' ) {
			$result = ( new WebhookAction( $this->settings, $logger ) )->sendTest();
		} elseif ( $channel === 'cloudflare_verify' ) {
			$result = ( new CloudflarePurgeAction( $this->settings, $logger ) )->verifyConnection();
		} elseif ( $channel === 'cloudflare_purge' ) {
			$result = ( new CloudflarePurgeAction( $this->settings, $logger ) )->purgeNow();
		} else {
			wp_send_json_error( array( 'message' => __( '不明な送信先です。', 'next-staatic-actions' ) ), 400 );

			return;
		}

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		}
		wp_send_json_error( $result );
	}
}
