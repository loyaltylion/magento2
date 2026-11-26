<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model\Outbox;

use Loyaltylion\Core\Model\Outbox\Outbox;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OutboxTest extends TestCase
{
    /** @var AdapterInterface&MockObject */
    private $db;

    private Outbox $outbox;

    protected function setUp(): void
    {
        $this->db = $this->createMock(AdapterInterface::class);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->db);
        $resource->method('getTableName')->willReturnArgument(0);

        $this->outbox = new Outbox($resource);
    }

    public function testASentRowIsForgottenOnlyIfTheEntityHasNotChangedSince(): void
    {
        $this->db->expects($this->once())->method('delete')
            ->with('loyaltylion_outbox', ['id = ?' => 9, 'version = ?' => 3]);

        $this->outbox->sent(['id' => 9, 'version' => 3]);
    }

    public function testARefusalOfAnOlderStateLeavesARowTheEntityChangedSince(): void
    {
        $this->db->expects($this->once())->method('update')
            ->with('loyaltylion_outbox', $this->anything(), ['id = ?' => 9, 'version = ?' => 3]);

        $this->outbox->park(['id' => 9, 'version' => 3], '422 refused');
    }

    public function testParkedRowsOfOneWebsiteAreQueuedAgainAsNew(): void
    {
        $this->db->expects($this->once())->method('update')
            ->with(
                'loyaltylion_outbox',
                $this->callback(static fn (array $set) => $set['state'] === 'waiting' && $set['attempts'] === 0),
                ['state = ?' => 'parked', 'website_id = ?' => 2]
            )
            ->willReturn(5);

        $this->assertSame(5, $this->outbox->retryParked(2));
    }
}
