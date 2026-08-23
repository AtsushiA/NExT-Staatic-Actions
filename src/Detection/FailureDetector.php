<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Detection;

use NExT\StaaticActions\Logging\DebugLogger;
use NExT\StaaticActions\Support\PublicationPayload;
use Staatic\WordPress\Publication\Publication;

final class FailureDetector
{
    /** @var NotificationGuard */
    private $guard;

    /** @var DebugLogger */
    private $logger;

    /** @var Publication|null */
    private $publication;

    public function __construct(NotificationGuard $guard, DebugLogger $logger)
    {
        $this->guard = $guard;
        $this->logger = $logger;
    }

    public function registerHooks(): void
    {
        add_action('staatic_publication_task_any', [$this, 'captureReference'], 10, 3);
        add_action('shutdown', [$this, 'checkForFailure']);
    }

    /**
     * @param Publication $publication
     */
    public function captureReference($publication): void
    {
        $this->publication = $publication;
    }

    public function checkForFailure(): void
    {
        if ($this->publication === null) {
            return;
        }
        if (!$this->publication->status()->isFailed()) {
            return;
        }
        if (!$this->guard->shouldNotify($this->publication->id(), 'failed')) {
            return;
        }
        $this->logger->log(sprintf('Publish failed for publication #%s', $this->publication->id()));
        do_action(
            'next_staatic_actions_publish_failed',
            PublicationPayload::fromPublication(
                $this->publication,
                __('Publication failed. See Staatic > Publications for details.', 'next-staatic-actions')
            )
        );
    }
}
