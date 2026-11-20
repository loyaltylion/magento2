<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Outbox;

/**
 * What an answer from LoyaltyLion means for the outbox row that was sent.
 */
class Outcome
{
    public const SENT = 'sent';
    public const HELD = 'held';
    public const REFUSED_KEY = 'refused_key';
    public const RETRY = 'retry';
    public const PARKED = 'parked';

    private const LONGEST_WAIT_SECONDS = 6 * 3600;

    /**
     * @param int $status the HTTP status, or 0 when LoyaltyLion could not be reached
     */
    public static function of(int $status): string
    {
        // The program has not launched: LoyaltyLion keeps nothing yet.
        if ($status === 202) {
            return self::HELD;
        }
        if ($status >= 200 && $status < 300) {
            return self::SENT;
        }
        if ($status === 401 || $status === 403) {
            return self::REFUSED_KEY;
        }
        // Timeouts, conflicts with a send in flight, rate limits and anything
        // that is LoyaltyLion's fault are worth another try; any other refusal
        // is about this entity and will be refused again.
        if ($status === 0 || $status === 408 || $status === 409 || $status === 429 || $status >= 500) {
            return self::RETRY;
        }

        return self::PARKED;
    }

    /**
     * Seconds to wait before try number $attempts + 1: one minute, doubling,
     * and never longer than six hours. A row is retried for as long as it takes.
     */
    public static function waitBeforeRetry(int $attempts): int
    {
        return (int) min(60 * (2 ** min($attempts, 20)), self::LONGEST_WAIT_SECONDS);
    }
}
