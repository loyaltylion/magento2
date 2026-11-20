<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model\Outbox;

use Loyaltylion\Core\Model\Outbox\Outcome;
use PHPUnit\Framework\TestCase;

class OutcomeTest extends TestCase
{
    /**
     * @dataProvider answers
     */
    public function testAnAnswerDecidesWhatHappensToTheRow(int $status, string $outcome): void
    {
        $this->assertSame($outcome, Outcome::of($status));
    }

    public static function answers(): array
    {
        return [
            'accepted' => [200, Outcome::SENT],
            'program not launched' => [202, Outcome::HELD],
            'key refused' => [401, Outcome::REFUSED_KEY],
            'key lacks a scope' => [403, Outcome::REFUSED_KEY],
            'could not reach LoyaltyLion' => [0, Outcome::RETRY],
            'a send in flight' => [409, Outcome::RETRY],
            'rate limited' => [429, Outcome::RETRY],
            'LoyaltyLion failed' => [503, Outcome::RETRY],
            'this entity is refused' => [422, Outcome::PARKED],
        ];
    }

    public function testTheWaitDoublesFromAMinuteAndStopsAtSixHours(): void
    {
        $this->assertSame(60, Outcome::waitBeforeRetry(0));
        $this->assertSame(240, Outcome::waitBeforeRetry(2));
        $this->assertSame(21600, Outcome::waitBeforeRetry(9));
        $this->assertSame(21600, Outcome::waitBeforeRetry(500));
    }
}
