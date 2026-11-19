<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class OrderUpdateObserver implements ObserverInterface
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
            $order = $observer->getEvent()->getOrder();
            if (!$order) {
                return;
            }

            // It's important to fetch our credentials with the storeId saved to
            // our order here: it's possible to update orders from different contexts,
            // so the general purpose scopeConfig->getValue would potentially return
            // a different storeId here
            list(, , $orders) = $this->_config->getClientForStore(
                $order->getStoreId()
            );
            if (!$orders) {
                // We aren't enabled in the website/store this order was placed in
                return;
            }

            if (!$order->getId()) {
                return;
            }

            // The body is built when the update is sent, from the order as it
            // is then, so several saves collapse into one up-to-date update.
            $this->_queue->add(
                $order->getStoreId(),
                \Loyaltylion\Core\Model\Outbox::KIND_ORDER_UPDATE,
                "order:" . $order->getId(),
                null,
                \Loyaltylion\Core\Model\Outbox::ORDER_UPDATE_DELAY_SECONDS
            );
        } catch (\Exception $e) {
            $this->_logger->error(
                "[LoyaltyLion] Unexpected error: " . $e->getMessage()
            );
        }
    }
}
