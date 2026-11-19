<?php

namespace Loyaltylion\Core\Model\Observer;

use Loyaltylion\Core\Model\Outbox;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Newsletter\Model\Subscriber;

class NewsletterSubscribeObserver implements ObserverInterface
{
    private $_config;
    private $_logger;
    private $_queue;

    public function __construct(
        \Loyaltylion\Core\Helper\Config $config,
        \Psr\Log\LoggerInterface $logger,
        \Loyaltylion\Core\Model\EventQueue $queue
    ) {
        $this->_config = $config;
        $this->_logger = $logger;
        $this->_queue = $queue;
    }

    public function execute(Observer $observer)
    {
        try {
            $subscriber = $observer->getEvent()->getSubscriber();
            // A guest subscriber has no customer we could reward.
            if (!$subscriber || !$subscriber->getCustomerId()) {
                return;
            }
            $subscribed = (int) $subscriber->getSubscriberStatus() === Subscriber::STATUS_SUBSCRIBED;
            $wasSubscribed = (int) $subscriber->getOrigData("subscriber_status") === Subscriber::STATUS_SUBSCRIBED;
            if (!$subscribed || $wasSubscribed) {
                return;
            }
            list($connection) = $this->_config->getClientForStore($subscriber->getStoreId());
            if (!$connection) {
                return;
            }
            $this->_queue->add(
                $subscriber->getStoreId(),
                Outbox::KIND_NEWSLETTER_SIGNUP,
                "customer:" . $subscriber->getCustomerId(),
                []
            );
        } catch (\Exception $e) {
            $this->_logger->error("[LoyaltyLion] Unexpected error: " . $e->getMessage());
        }
    }
}
