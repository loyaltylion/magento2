<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Outbox;

class Timestamp
{
    /**
     * Magento keeps its timestamps in UTC and hands them out with no zone,
     * which a reader in another zone takes for its own local time.
     */
    public static function utc(string $magentoTimestamp): string
    {
        return (new \DateTimeImmutable($magentoTimestamp, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
