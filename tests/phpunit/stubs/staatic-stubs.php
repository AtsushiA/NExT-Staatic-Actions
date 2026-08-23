<?php
/**
 * Minimal stand-ins for the Staatic plugin's public class shapes.
 *
 * Staatic is a commercial plugin not available in CI, so its real classes
 * cannot be installed here. These stubs re-implement only the accessor
 * methods this plugin actually calls, matching Staatic 1.12.5's public
 * signatures, so Detection/* classes can be exercised without redistributing
 * any of Staatic's own source.
 */

namespace {
	if ( ! defined( 'STAATIC_VERSION' ) ) {
		define( 'STAATIC_VERSION', 'stub' );
	}
}

namespace Staatic\Framework {

	final class Build {

		private $destinationUrl;
		private $entryUrl;
		private $numUrlsCrawled;

		public function __construct( string $destinationUrl = '', string $entryUrl = '', int $numUrlsCrawled = 0 ) {
			$this->destinationUrl = $destinationUrl;
			$this->entryUrl       = $entryUrl;
			$this->numUrlsCrawled = $numUrlsCrawled;
		}

		public function destinationUrl(): string {
			return $this->destinationUrl;
		}

		public function entryUrl(): string {
			return $this->entryUrl;
		}

		public function numUrlsCrawled(): int {
			return $this->numUrlsCrawled;
		}
	}

	final class Deployment {

		private $numResultsDeployed;

		public function __construct( int $numResultsDeployed = 0 ) {
			$this->numResultsDeployed = $numResultsDeployed;
		}

		public function numResultsDeployed(): int {
			return $this->numResultsDeployed;
		}
	}
}

namespace Staatic\WordPress\Publication {

	final class PublicationStatus {

		public const STATUS_PENDING     = 'pending';
		public const STATUS_IN_PROGRESS = 'in_progress';
		public const STATUS_FINISHED    = 'finished';
		public const STATUS_CANCELED    = 'canceled';
		public const STATUS_FAILED      = 'failed';

		private $status;

		private function __construct( string $status ) {
			$this->status = $status;
		}

		public static function create( string $status ): self {
			return new self( $status );
		}

		public function __toString(): string {
			return $this->status;
		}

		public function isPending(): bool {
			return $this->status === self::STATUS_PENDING;
		}

		public function isInProgress(): bool {
			return $this->status === self::STATUS_IN_PROGRESS;
		}

		public function isFinished(): bool {
			return $this->status === self::STATUS_FINISHED;
		}

		public function isCanceled(): bool {
			return $this->status === self::STATUS_CANCELED;
		}

		public function isFailed(): bool {
			return $this->status === self::STATUS_FAILED;
		}
	}

	final class Publication {

		private $id;
		private $dateCreated;
		private $build;
		private $deployment;
		private $isPreview;
		private $userId;
		private $status;
		private $dateFinished;
		private $currentTask;

		public function __construct(
			string $id,
			\DateTimeInterface $dateCreated,
			\Staatic\Framework\Build $build,
			\Staatic\Framework\Deployment $deployment,
			bool $isPreview = false,
			?int $userId = null
		) {
			$this->id          = $id;
			$this->dateCreated = $dateCreated;
			$this->build       = $build;
			$this->deployment  = $deployment;
			$this->isPreview   = $isPreview;
			$this->userId      = $userId;
			$this->status      = PublicationStatus::create( PublicationStatus::STATUS_PENDING );
		}

		public function id(): string {
			return $this->id;
		}

		public function dateCreated(): \DateTimeInterface {
			return $this->dateCreated;
		}

		public function build(): \Staatic\Framework\Build {
			return $this->build;
		}

		public function deployment(): \Staatic\Framework\Deployment {
			return $this->deployment;
		}

		public function isPreview(): bool {
			return $this->isPreview;
		}

		public function userId(): ?int {
			return $this->userId;
		}

		public function publisher(): ?\WP_User {
			return $this->userId ? get_userdata( $this->userId ) ?: null : null;
		}

		public function status(): PublicationStatus {
			return $this->status;
		}

		public function dateFinished(): ?\DateTimeInterface {
			return $this->dateFinished;
		}

		public function currentTask(): ?string {
			return $this->currentTask;
		}

		public function setCurrentTask( ?string $currentTask ): void {
			$this->currentTask = $currentTask;
		}

		public function markInProgress(): void {
			$this->status = PublicationStatus::create( PublicationStatus::STATUS_IN_PROGRESS );
		}

		public function markCanceled(): void {
			$this->status = PublicationStatus::create( PublicationStatus::STATUS_CANCELED );
		}

		public function markFailed(): void {
			$this->status = PublicationStatus::create( PublicationStatus::STATUS_FAILED );
		}

		public function markFinished(): void {
			$this->status       = PublicationStatus::create( PublicationStatus::STATUS_FINISHED );
			$this->dateFinished = new \DateTimeImmutable();
		}
	}
}

namespace Staatic\WordPress\Publication\Task {

	interface TaskInterface {

		public static function name(): string;
	}

	final class SetupTask implements TaskInterface {

		public static function name(): string {
			return 'setup';
		}
	}

	final class FinishTask implements TaskInterface {

		public static function name(): string {
			return 'finish';
		}
	}
}
