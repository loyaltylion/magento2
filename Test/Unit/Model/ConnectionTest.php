<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model;

use Loyaltylion\Core\Model\Connection;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    private function connection(array $settings): Connection
    {
        return new Connection(
            $settings['siteId'] ?? null,
            $settings['siteToken'] ?? null,
            $settings['apiKey'] ?? null,
            $settings['signingKey'] ?? null,
            'api.example.com',
            'sdk.example.com',
            false,
            $settings['connectedByOldModule'] ?? false,
            $settings['hasSdkToken'] ?? false
        );
    }

    public function testAStoreJustUpgradedKeepsItsChangesAndItsStorefrontButSendsNothingYet(): void
    {
        $upgraded = $this->connection([
            'siteToken' => 'old-token',
            'signingKey' => 'old-secret',
            'connectedByOldModule' => true,
        ]);

        $this->assertTrue($upgraded->queuesChanges());
        $this->assertFalse($upgraded->sendsChanges());
        $this->assertTrue($upgraded->signsShoppers());
        $this->assertTrue($upgraded->needsNewSettings());
    }

    public function testAnUpgradedStoreStillNeedsItsSdkTokenOnceItCanSend(): void
    {
        $halfDone = $this->connection([
            'siteId' => 15,
            'siteToken' => 'old-token',
            'apiKey' => 'pat_key',
            'signingKey' => 'old-secret',
            'connectedByOldModule' => true,
        ]);

        $this->assertTrue($halfDone->sendsChanges());
        $this->assertTrue($halfDone->needsNewSettings());
    }

    public function testAFullyConnectedStoreNeedsNothing(): void
    {
        $connected = $this->connection([
            'siteId' => 15,
            'siteToken' => 'token',
            'apiKey' => 'pat_key',
            'signingKey' => 'sdk-secret',
            'connectedByOldModule' => true,
            'hasSdkToken' => true,
        ]);

        $this->assertTrue($connected->sendsChanges());
        $this->assertFalse($connected->needsNewSettings());
    }

    public function testAWebsiteNeverConnectedNotesNothing(): void
    {
        $unconnected = $this->connection([]);

        $this->assertFalse($unconnected->queuesChanges());
        $this->assertFalse($unconnected->showsSdk());
        $this->assertFalse($unconnected->needsNewSettings());
    }
}
