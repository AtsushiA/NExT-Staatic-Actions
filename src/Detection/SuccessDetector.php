<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Detection;

use NExT\StaaticActions\Logging\DebugLogger;
use NExT\StaaticActions\Support\PublicationPayload;
use Staatic\WordPress\Publication\Publication;
use Staatic\WordPress\Publication\Task\FinishTask;
use Staatic\WordPress\Publication\Task\TaskInterface;

final class SuccessDetector {

	/** @var NotificationGuard */
	private $guard;

	/** @var DebugLogger */
	private $logger;

	public function __construct( NotificationGuard $guard, DebugLogger $logger ) {
		$this->guard  = $guard;
		$this->logger = $logger;
	}

	public function registerHooks(): void {
		add_action( 'staatic_publication_task_after', array( $this, 'onTaskAfter' ), 10, 2 );
	}

	/**
	 * @param Publication   $publication
	 * @param TaskInterface $task
	 */
	public function onTaskAfter( $publication, $task ): void {
		if ( ! ( $task instanceof FinishTask ) ) {
			return;
		}
		if ( ! $publication->status()->isFinished() ) {
			return;
		}
		if ( ! $this->guard->shouldNotify( $publication->id(), 'succeeded' ) ) {
			return;
		}
		$this->logger->log( sprintf( 'Publish succeeded for publication #%s', $publication->id() ) );
		do_action( 'next_staatic_actions_publish_succeeded', PublicationPayload::fromPublication( $publication ) );
	}
}
