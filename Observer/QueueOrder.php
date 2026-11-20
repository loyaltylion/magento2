<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Observer;

use Loyaltylion\Core\Model\Outbox\Queue;
use Loyaltylion\Core\Model\Outbox\Outbox;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class QueueOrder implements ObserverInterface
{
    public function __construct(private readonly Queue $queue)
    {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();

        $this->queue->add(Outbox::ORDER, $order->getEntityId(), $order->getStore()->getWebsiteId());
    }
}
