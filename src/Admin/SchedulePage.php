<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Admin;

final class SchedulePage {

	public const CAPABILITY = 'next_staatic_actions_manage_schedule';

	private const PAGE_SLUG    = 'next-staatic-actions-schedule';
	private const NONCE_ACTION = 'next_staatic_actions_save_schedule';

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
		// admin_init alone is enough to stay in sync promptly: it fires on
		// literally the very next admin request from anyone (including the
		// admin who just toggled the setting, via the post-save redirect, or
		// an editor's own next page load), without reacting to every single
		// update_option() call site-wide the way an update_option_{name}
		// hook would.
		add_action( 'admin_init', array( $this, 'syncEditorCapability' ) );
		add_action( 'admin_post_' . self::NONCE_ACTION, array( $this, 'handleSave' ) );
	}

	/**
	 * Administrators always have this capability. Whether the editor role
	 * also has it follows the "編集者にスケジュール公開の操作を許可"
	 * toggle on the Actions > 詳細設定 tab; this both grants and revokes
	 * as needed (cheap in-memory has_cap() check, only writes when the
	 * state actually needs to change), running on admin_init as a self-heal
	 * and immediately whenever the settings are saved.
	 */
	public function syncEditorCapability(): void {
		$administrator = get_role( 'administrator' );
		if ( $administrator && ! $administrator->has_cap( self::CAPABILITY ) ) {
			$administrator->add_cap( self::CAPABILITY );
		}

		$editor = get_role( 'editor' );
		if ( ! $editor ) {
			return;
		}

		$editorAccessEnabled = $this->settings->get()['schedule_editor_access_enabled'];
		if ( $editorAccessEnabled && ! $editor->has_cap( self::CAPABILITY ) ) {
			$editor->add_cap( self::CAPABILITY );
		} elseif ( ! $editorAccessEnabled && $editor->has_cap( self::CAPABILITY ) ) {
			$editor->remove_cap( self::CAPABILITY );
		}
	}

	public function registerMenu(): void {
		if ( ! defined( 'STAATIC_VERSION' ) ) {
			return;
		}

		add_submenu_page(
			'staatic',
			__( 'スケジュール公開', 'next-staatic-actions' ),
			__( 'スケジュール公開', 'next-staatic-actions' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'renderPage' )
		);
	}

	public function renderPage(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings = $this->settings->get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'スケジュール公開', 'next-staatic-actions' ); ?></h1>
			<p><?php esc_html_e( '指定した時刻に Staatic の公開（静的ジェネレート）を自動実行します。時刻はサイトのタイムゾーンで判定されます。', 'next-staatic-actions' ); ?></p>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash notice, not a state-changing action. ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '設定を保存しました。', 'next-staatic-actions' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::NONCE_ACTION ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'スケジュール公開を有効化', 'next-staatic-actions' ); ?></th>
						<td><?php $this->fieldCheckbox( 'schedule_enabled', $settings ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '頻度', 'next-staatic-actions' ); ?></th>
						<td><?php $this->fieldModeSelect( $settings ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '日付（頻度が「指定日時」の場合のみ）', 'next-staatic-actions' ); ?></th>
						<td><?php $this->fieldDate( $settings ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '曜日（頻度が「毎週指定曜日」の場合のみ）', 'next-staatic-actions' ); ?></th>
						<td><?php $this->fieldWeekdays( $settings ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '時刻', 'next-staatic-actions' ); ?></th>
						<td><?php $this->fieldTime( $settings ); ?></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function handleSave(): void {
		check_admin_referer( self::NONCE_ACTION );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'このページにアクセスする権限がありません。', 'next-staatic-actions' ), 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- each field is sanitized individually by Settings::sanitizeScheduleOnly() below.
		$posted = $_POST[ Settings::OPTION_NAME ] ?? array();
		$merged = $this->settings->sanitizeScheduleOnly( $posted );
		update_option( Settings::OPTION_NAME, $merged );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function fieldCheckbox( string $key, array $settings ): void {
		printf(
			'<input type="checkbox" name="%1$s[%2$s]" value="1" %3$s />',
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( $key ),
			checked( $settings[ $key ], true, false )
		);
	}

	private function fieldModeSelect( array $settings ): void {
		$value = $settings['schedule_mode'];
		$modes = array(
			'one_time' => __( '指定日時に1回だけ実行', 'next-staatic-actions' ),
			'daily'    => __( '毎日実行', 'next-staatic-actions' ),
			'weekly'   => __( '毎週指定曜日に実行', 'next-staatic-actions' ),
		);
		printf( '<select name="%1$s[schedule_mode]">', esc_attr( Settings::OPTION_NAME ) );
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

	private function fieldTime( array $settings ): void {
		printf(
			'<input type="time" name="%1$s[schedule_time]" value="%2$s" />',
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( $settings['schedule_time'] )
		);
	}

	private function fieldDate( array $settings ): void {
		printf(
			'<input type="date" name="%1$s[schedule_date]" value="%2$s" />',
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( $settings['schedule_date'] )
		);
	}

	private function fieldWeekdays( array $settings ): void {
		$value    = $settings['schedule_weekdays'];
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
				'<label style="margin-right:1em;"><input type="checkbox" name="%1$s[schedule_weekdays][]" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( Settings::OPTION_NAME ),
				esc_attr( $day ),
				checked( in_array( $day, $value, true ), true, false ),
				esc_html( $label )
			);
		}
	}
}
