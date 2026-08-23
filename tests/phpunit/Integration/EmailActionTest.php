<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Tests\Integration;

use NExT\StaaticActions\Action\EmailAction;
use NExT\StaaticActions\Admin\Settings;
use NExT\StaaticActions\Logging\DebugLogger;
use WP_UnitTestCase;

final class EmailActionTest extends WP_UnitTestCase {

	private EmailAction $action;

	public function set_up(): void {
		parent::set_up();
		$this->action = new EmailAction( new Settings(), new DebugLogger( new Settings() ) );
		reset_phpmailer_instance();
	}

	public function tear_down(): void {
		reset_phpmailer_instance();
		parent::tear_down();
	}

	public function test_send_test_reports_failure_when_no_recipients_configured(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'email_recipients' => '',
				)
			)
		);

		$result = $this->action->sendTest();

		self::assertFalse( $result['success'] );
		self::assertStringContainsString( 'no recipients', $result['message'] );
	}

	public function test_send_test_sends_to_configured_recipients_with_rendered_placeholders(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				array(
					'email_recipients' => 'a@example.com, b@example.com',
					'email_subject'    => 'Subject: {{status}}',
					'email_body'       => 'Body: {{publication_id}}',
				)
			)
		);

		$result = $this->action->sendTest();

		self::assertTrue( $result['success'] );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent   = $mailer->get_sent();
		self::assertSame( 'Subject: test', $sent->subject );
		self::assertStringContainsString( 'Body: test-', $sent->body );
		self::assertSame( 'a@example.com', $mailer->get_recipient( 'to', 0, 0 )->address );
		self::assertSame( 'b@example.com', $mailer->get_recipient( 'to', 0, 1 )->address );
	}
}
