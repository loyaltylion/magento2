<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Observer;

use Loyaltylion\Core\Model\Outbox\Queue;
use Loyaltylion\Core\Model\Outbox\Outbox;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class QueueCustomer implements ObserverInterface
{
    public function __construct(private readonly Queue $queue)
    {
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomer();

        // LoyaltyLion has nothing to bring up to date for a customer it has
        // not met; a shopper's own registration is queued by QueueRegistration.
        if ($customer->isObjectNew()) {
            return;
        }

        $this->queue->add(Outbox::CUSTOMER, $customer->getId(), $customer->getWebsiteId());
    }
}
