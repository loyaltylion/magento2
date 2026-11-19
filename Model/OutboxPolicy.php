<?php

namespace Loyaltylion\Core\Model;

/**
 * Decides what happens to an outbox event after a send attempt.
 */
class OutboxPolicy
{
    const SENT = "sent";
    /** The failure is about the store or our service, not this event: retry it and hold the store's other events. */
    const RETRY = "retry";
    /** Our API rejected this event, so sending it again would fail the same way. */
    const PARK = "park";

    const FIRST_DELAY_SECONDS = 60;
    const MAX_DELAY_SECONDS = 3600;
    // Long enough to keep the sign-ups and orders a store takes while its
    // program is still being set up, until the merchant launches it.
    const GIVE_UP_AFTER_SECONDS = 30 * 86400;

    /**
     * @param int|null $status HTTP status, or null when no response came back (network error, timeout)
     * @param string   $body   Response body
     */
    public static function classify($status, $body)
    {
        if ($status === null || $status === 0) {
            return self::RETRY;
        }
        if ($status >= 200 && $status < 300) {
            return self::SENT;
        }
        if ($status >= 500 || $status == 429 || $status == 401 || $status == 408) {
            return self::RETRY;
        }
        if ($status == 403 && stripos((string) $body, "Program is not live") !== false) {
            return self::RETRY;
        }
        return self::PARK;
    }

    /**
     * Seconds to wait before the next attempt, given how many attempts have failed so far.
     */
    public static function backoffSeconds($failedAttempts)
    {
        $exponent = max(0, min(20, $failedAttempts - 1));
        return (int) min(self::FIRST_DELAY_SECONDS * (2 ** $exponent), self::MAX_DELAY_SECONDS);
    }

    public static function shouldGiveUp($createdAtTimestamp, $nowTimestamp)
    {
        return $nowTimestamp - $createdAtTimestamp > self::GIVE_UP_AFTER_SECONDS;
    }
}
