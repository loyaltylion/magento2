<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model\Shopper;

use Loyaltylion\Core\Model\Shopper\Signer;
use PHPUnit\Framework\TestCase;

class SignerTest extends TestCase
{
    public function testThePacketIsSignedAsTheSdkDocumentationSetsOut(): void
    {
        $packet = Signer::sign('42', 'shopper@example.com', '2026-01-31T10:00:00+00:00', 'sdk-secret');

        $this->assertSame(
            [
                'customer' => ['id' => '42', 'email' => 'shopper@example.com'],
                'auth' => [
                    'date' => '2026-01-31T10:00:00+00:00',
                    'token' => sha1('42' . '2026-01-31T10:00:00+00:00' . 'shopper@example.com' . 'sdk-secret'),
                ],
            ],
            $packet
        );
    }

    public function testAnotherCustomersEmailBesideTheSameIdDoesNotCarryTheSignature(): void
    {
        $date = '2026-01-31T10:00:00+00:00';

        $this->assertNotSame(
            Signer::sign('42', 'shopper@example.com', $date, 'sdk-secret')['auth']['token'],
            Signer::sign('42', 'someone-else@example.com', $date, 'sdk-secret')['auth']['token']
        );
    }
}
