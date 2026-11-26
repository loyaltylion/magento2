<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Console\Command;

use Loyaltylion\Core\Console\Command\RetryParkedCommand;
use Loyaltylion\Core\Model\Outbox\Outbox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class RetryParkedCommandTest extends TestCase
{
    /**
     * @dataProvider websites
     */
    public function testItQueuesAgainTheParkedRowsOfTheWebsiteAsked(array $input, ?int $websiteId): void
    {
        $outbox = $this->createMock(Outbox::class);
        $outbox->method('summary')->willReturn(['waiting' => 3, 'parked' => 0, 'oldest_waiting_at' => null]);

        $outbox->expects($this->once())->method('retryParked')->with($websiteId)->willReturn(3);

        $tester = new CommandTester(new RetryParkedCommand($outbox));
        $tester->execute($input);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame(3, json_decode($tester->getDisplay(), true)['queued_again']);
    }

    public static function websites(): array
    {
        return [
            'one website' => [['--website' => '2'], 2],
            'every website' => [[], null],
        ];
    }
}
