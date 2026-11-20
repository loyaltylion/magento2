<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Observer;

use Loyaltylion\Core\Model\Outbox\Outbox;
use Loyaltylion\Core\Model\Outbox\Queue;
use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Tells LoyaltyLion when a seamless free product leaves the cart, so the
 * shopper gets their points back without waiting for the claim to expire.
 */
class ReleaseRemovedRewardLine implements ObserverInterface
{
    public function __construct(
        private readonly FreeProduct $freeProduct,
        private readonly Queue $queue
    ) {
    }

    public function execute(Observer $observer): void
    {
        $item = $observer->getEvent()->getQuoteItem();
        $session = $this->freeProduct->sessionOf($item);
        $quote = $item->getQuote();

        if ($session === null || !$quote || !$quote->getCustomerId()) {
            return;
        }

        $this->queue->add(
            Outbox::CART_SESSION_RELEASED,
            $item->getId(),
            $quote->getStore()->getWebsiteId(),
            ['customer_id' => (int) $quote->getCustomerId(), 'session' => $session]
        );
    }
}
