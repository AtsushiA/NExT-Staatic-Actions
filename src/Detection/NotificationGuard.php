<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Detection;

final class NotificationGuard
{
    public function shouldNotify(string $publicationId, string $status): bool
    {
        $key = 'nsa_notified_' . $publicationId;
        if (get_transient($key) === $status) {
            return false;
        }
        set_transient($key, $status, DAY_IN_SECONDS);

        return true;
    }
}
