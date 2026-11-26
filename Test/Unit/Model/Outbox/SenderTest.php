<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model\Outbox;

use Loyaltylion\Core\Model\Api\Client;
use Loyaltylion\Core\Model\Api\Response;
use Loyaltylion\Core\Model\Config;
use Loyaltylion\Core\Model\Connection;
use Loyaltylion\Core\Model\Outbox\CustomerState;
use Loyaltylion\Core\Model\Outbox\OrderState;
use Loyaltylion\Core\Model\Outbox\Outbox;
use Loyaltylion\Core\Model\Outbox\Sender;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SenderTest extends TestCase
{
    /** @var Outbox&MockObject */
    private $outbox;

    /** @var Client&MockObject */
    private $client;

    /** @var int[] the order ids sent, in order */
    private array $sent = [];

    private int $now = 1000;

    /** How long LoyaltyLion takes to answer each send */
    private int $secondsPerSend = 0;

    private Sender $sender;

    protected function setUp(): void
    {
        $this->outbox = $this->createMock(Outbox::class);
        $this->client = $this->createMock(Client::class);
        $clock = $this->createMock(DateTime::class);
        $clock->method('gmtTimestamp')->willReturnCallback(fn () => $this->now);

        $config = $this->createMock(Config::class);
        $config->method('forWebsite')->willReturn(
            new Connection(15, 'token', 'pat_key', 'secret', 'api.example.com', 'sdk', 'static', 'platform')
        );
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('get')->willReturnCallback(function ($id) {
            $order = $this->createMock(OrderInterface::class);
            $order->method('getEntityId')->willReturn($id);

            return $order;
        });
        $orderState = $this->createMock(OrderState::class);
        $orderState->method('of')->willReturnCallback(static fn ($order) => ['entity_id' => $order->getEntityId()]);
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->method('lock')->willReturn(true);

        $this->sender = new Sender(
            $this->outbox,
            $config,
            $this->client,
            $orders,
            $this->createMock(CustomerRepositoryInterface::class),
            $orderState,
            $this->createMock(CustomerState::class),
            $lock,
            $this->createMock(LoggerInterface::class),
            $clock
        );
    }

    public function testAWebsiteWhoseProgramHasNotLaunchedIsHeldWithoutHoldingUpTheNext(): void
    {
        $this->due([1 => [11, 12], 2 => [21]]);
        $this->answers([11 => 202, 21 => 200]);

        $this->outbox->expects($this->once())->method('holdWebsite')->with(1, $this->anything(), $this->anything());

        $this->sender->send();

        $this->assertSame([11, 21], $this->sent);
    }

    public function testAWebsiteWhoseKeyIsRefusedIsHeldWithoutHoldingUpTheNext(): void
    {
        $this->due([1 => [11, 12], 2 => [21]]);
        $this->answers([11 => 401, 21 => 200]);

        $this->outbox->expects($this->once())->method('holdWebsite')->with(1, $this->anything(), $this->anything());

        $this->sender->send();

        $this->assertSame([11, 21], $this->sent);
    }

    public function testARunStopsWhenLoyaltyLionCannotBeReachedTwiceRunning(): void
    {
        $this->due([1 => [11, 12, 13], 2 => [21]]);
        $this->answers([11 => 0, 12 => 0]);

        $this->sender->send();

        $this->assertSame([11, 12], $this->sent);
    }

    public function testARunStopsSendingOnceItsTimeIsUp(): void
    {
        $this->due([1 => [11, 12], 2 => [21]]);
        $this->answers([]);
        $this->secondsPerSend = 40;

        $this->sender->send();

        $this->assertSame([11], $this->sent);
    }

    /**
     * @param array<int, int[]> $orderIdsByWebsite
     */
    private function due(array $orderIdsByWebsite): void
    {
        $this->outbox->method('dueWebsites')->willReturn(array_keys($orderIdsByWebsite));
        $this->outbox->method('due')->willReturnCallback(static fn ($websiteId) => array_map(
            static fn ($id) => ['id' => $id, 'kind' => Outbox::ORDER, 'entity_id' => $id, 'version' => 1, 'attempts' => 0],
            $orderIdsByWebsite[$websiteId]
        ));
    }

    /**
     * @param array<int, int> $statusByOrderId every other order is accepted
     */
    private function answers(array $statusByOrderId): void
    {
        $this->client->method('send')->willReturnCallback(function ($connection, $method, $path) use ($statusByOrderId) {
            $id = (int) basename($path);
            $this->sent[] = $id;
            $this->now += $this->secondsPerSend;

            return new Response($statusByOrderId[$id] ?? 200, '');
        });
    }
}
