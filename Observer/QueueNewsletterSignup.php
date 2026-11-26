<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Observer;

use Loyaltylion\Core\Model\Outbox\Outbox;
use Loyaltylion\Core\Model\Outbox\Queue;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Notes a customer subscribing to the newsletter, which a newsletter sign-up
 * rule rewards. Only the change to subscribed counts, so saving a subscriber
 * who already was says nothing. A guest subscriber has no customer to reward.
 */
class QueueNewsletterSignup implements ObserverInterface
{
    public function __construct(
        private readonly Queue $queue,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            /** @var Subscriber|null $subscriber */
            $subscriber = $observer->getEvent()->getSubscriber();
            if (!$subscriber || !$subscriber->getCustomerId()) {
                return;
            }

            $subscribed = (int) $subscriber->getSubscriberStatus() === Subscriber::STATUS_SUBSCRIBED;
            $wasSubscribed = (int) $subscriber->getOrigData('subscriber_status') === Subscriber::STATUS_SUBSCRIBED;
            if (!$subscribed || $wasSubscribed) {
                return;
            }

            $this->queue->add(
                Outbox::NEWSLETTER_SIGNUP,
                $subscriber->getCustomerId(),
                $this->storeManager->getStore($subscriber->getStoreId())->getWebsiteId()
            );
        } catch (\Throwable $e) {
            // The shopper's subscription must go through whatever happens here.
            $this->logger->warning('[LoyaltyLion] A newsletter sign-up was not queued: ' . $e->getMessage());
        }
    }
}
