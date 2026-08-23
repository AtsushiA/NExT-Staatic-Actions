<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Admin;

final class SettingsPage {

	private const PAGE_SLUG    = 'next-staatic-actions';
	private const OPTION_GROUP = 'next_staatic_actions_settings_group';

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

		add_settings_section( 'nsa_cloudflare', __( 'Cloudflare キャッシュパージ', 'next-staatic-actions' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'cloudflare_enabled_success', __( '成功時にパージ', 'next-staatic-actions' ), array( $this, 'fieldCheckbox' ), self::PAGE_SLUG, 'nsa_cloudflare', array( 'key' => 'cloudflare_enabled_success' ) );
		add_settings_field( 'cloudflare_enabled_failure', __( '失敗時にパージ', 'next-staatic-actions' ), array( $this, 'fieldCheckbox' ), self::PAGE_SLUG, 'nsa_cloudflare', array( 'key' => 'cloudflare_enabled_failure' ) );
		add_settings_field( 'cloudflare_zone_id', __( 'Zone ID', 'next-staatic-actions' ), array( $this, 'fieldText' ), self::PAGE_SLUG, 'nsa_cloudflare', array( 'key' => 'cloudflare_zone_id' ) );
		add_settings_field( 'cloudflare_api_token', __( 'API トークン', 'next-staatic-actions' ), array( $this, 'fieldPassword' ), self::PAGE_SLUG, 'nsa_cloudflare', array( 'key' => 'cloudflare_api_token' ) );

		add_settings_section( 'nsa_webhook', __( 'Webhook 通知', 'next-staatic-actions' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'webhook_enabled_success', __( '成功時に送信', 'next-staatic-actions' ), array( $this, 'fieldCheckbox' ), self::PAGE_SLUG, 'nsa_webhook', array( 'key' => 'webhook_enabled_success' ) );
		add_settings_field( 'webhook_enabled_failure', __( '失敗時に送信', 'next-staatic-actions' ), array( $this, 'fieldCheckbox' ), self::PAGE_SLUG, 'nsa_webhook', array( 'key' => 'webhook_enabled_failure' ) );
		add_settings_field( 'webhook_url', __( 'URL', 'next-staatic-actions' ), array( $this, 'fieldText' ), self::PAGE_SLUG, 'nsa_webhook', array( 'key' => 'webhook_url' ) );
		add_settings_field( 'webhook_method', __( 'HTTPメソッド', 'next-staatic-actions' ), array( $this, 'fieldMethodSelect' ), self::PAGE_SLUG, 'nsa_webhook', array( 'key' => 'webhook_method' ) );
		add_settings_field( 'webhook_headers', __( 'ヘッダー（1行につき Name: value）', 'next-staatic-actions' ), array( $this, 'fieldTextarea' ), self::PAGE_SLUG, 'nsa_webhook', array( 'key' => 'webhook_headers' ) );
		add_settings_field( 'webhook_body', __( 'ボディ（空欄の場合はJSONを自動送信）', 'next-staatic-actions' ), array( $this, 'fieldTextarea' ), self::PAGE_SLUG, 'nsa_webhook', array( 'key' => 'webhook_body' ) );

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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NExT Staatic Actions', 'next-staatic-actions' ); ?></h1>
			<p><?php esc_html_e( 'プレースホルダー: {{status}} {{publication_id}} {{destination_url}} {{date_finished}} {{site_url}} {{admin_publication_url}} {{failure_message}}', 'next-staatic-actions' ); ?></p>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
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
}
