<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Support;

use Staatic\WordPress\Publication\Publication;

final class PublicationPayload {

	public static function fromPublication( Publication $publication, ?string $failureMessage = null ): array {
		$dateCreated  = $publication->dateCreated();
		$dateFinished = $publication->dateFinished();
		$publisher    = $publication->publisher();

		return array(
			'publication_id'        => $publication->id(),
			'status'                => (string) $publication->status(),
			'is_preview'            => $publication->isPreview(),
			'date_created'          => $dateCreated->format( DATE_ATOM ),
			'date_finished'         => $dateFinished ? $dateFinished->format( DATE_ATOM ) : '',
			'destination_url'       => (string) $publication->build()->destinationUrl(),
			'entry_url'             => (string) $publication->build()->entryUrl(),
			'num_urls_crawled'      => $publication->build()->numUrlsCrawled(),
			'num_results_deployed'  => $publication->deployment()->numResultsDeployed(),
			'user_id'               => $publication->userId() ?? '',
			'user_login'            => $publisher ? $publisher->user_login : '',
			'site_url'              => home_url(),
			'admin_publication_url' => admin_url( 'admin.php?page=staatic-publication&id=' . $publication->id() ),
			'failure_message'       => $failureMessage ?? '',
		);
	}

	public static function sample(): array {
		$now  = current_time( 'c' );
		$user = wp_get_current_user();

		return array(
			'publication_id'        => 'test-' . wp_generate_uuid4(),
			'status'                => 'test',
			'is_preview'            => false,
			'date_created'          => $now,
			'date_finished'         => $now,
			'destination_url'       => home_url(),
			'entry_url'             => home_url(),
			'num_urls_crawled'      => 0,
			'num_results_deployed'  => 0,
			'user_id'               => $user->ID,
			'user_login'            => $user->user_login,
			'site_url'              => home_url(),
			'admin_publication_url' => admin_url(),
			'failure_message'       => __( 'これはテスト送信です。', 'next-staatic-actions' ),
		);
	}
}
