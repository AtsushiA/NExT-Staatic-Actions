<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Support;

use Staatic\WordPress\Publication\Publication;

final class PublicationPayload
{
    public static function fromPublication(Publication $publication, string $failureMessage = null): array
    {
        $dateCreated = $publication->dateCreated();
        $dateFinished = $publication->dateFinished();
        $publisher = $publication->publisher();

        return [
            'publication_id' => $publication->id(),
            'status' => (string) $publication->status(),
            'is_preview' => $publication->isPreview(),
            'date_created' => $dateCreated->format(DATE_ATOM),
            'date_finished' => $dateFinished ? $dateFinished->format(DATE_ATOM) : '',
            'destination_url' => (string) $publication->build()->destinationUrl(),
            'entry_url' => (string) $publication->build()->entryUrl(),
            'num_urls_crawled' => $publication->build()->numUrlsCrawled(),
            'num_results_deployed' => $publication->deployment()->numResultsDeployed(),
            'user_id' => $publication->userId() ?? '',
            'user_login' => $publisher ? $publisher->user_login : '',
            'site_url' => home_url(),
            'admin_publication_url' => admin_url('admin.php?page=staatic-publication&id=' . $publication->id()),
            'failure_message' => $failureMessage ?? '',
        ];
    }
}
