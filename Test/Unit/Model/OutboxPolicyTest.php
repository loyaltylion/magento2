<?php

namespace Loyaltylion\Core\Test\Unit\Model;

use Loyaltylion\Core\Model\OutboxPolicy;

class OutboxPolicyTest extends \PHPUnit\Framework\TestCase
{
    public function testRetriesWhenOurServiceOrTheConnectionFails()
    {
        foreach ([null, 500, 502, 503, 429] as $status) {
            $this->assertSame(OutboxPolicy::RETRY, OutboxPolicy::classify($status, "error"));
        }
    }

    public function testRetriesWhileTheProgramIsNotLaunched()
    {
        $body = '{"error":{"message":"Program is not live: please launch the program before making API requests."}}';
        $this->assertSame(OutboxPolicy::RETRY, OutboxPolicy::classify(403, $body));
    }

    public function testParksAnEventTheApiRejects()
    {
        $this->assertSame(OutboxPolicy::PARK, OutboxPolicy::classify(400, '{"error":"customer_email is required"}'));
        $this->assertSame(OutboxPolicy::PARK, OutboxPolicy::classify(403, "Site has been uninstalled"));
        $this->assertSame(OutboxPolicy::PARK, OutboxPolicy::classify(404, '{"error":"No matching order found"}'));
    }

    public function testTreatsTheNotLaunchedAcceptAsSent()
    {
        $this->assertSame(OutboxPolicy::SENT, OutboxPolicy::classify(202, "Program is not live"));
    }

    public function testBacksOffUpToAnHour()
    {
        $this->assertSame(60, OutboxPolicy::backoffSeconds(1));
        $this->assertSame(240, OutboxPolicy::backoffSeconds(3));
        $this->assertSame(3600, OutboxPolicy::backoffSeconds(50));
    }

    public function testGivesUpAfterThirtyDays()
    {
        $now = 1_800_000_000;
        $this->assertFalse(OutboxPolicy::shouldGiveUp($now - 29 * 86400, $now));
        $this->assertTrue(OutboxPolicy::shouldGiveUp($now - 31 * 86400, $now));
    }
}
