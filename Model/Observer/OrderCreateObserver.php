<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class OrderCreateObserver implements ObserverInterface
{
    private $_config;
    private $_orderTools;
    private $_logger;
    private $_queue;

    public function __construct(
        \Loyaltylion\Core\Helper\Config $config,
        \Loyaltylion\Core\Helper\OrderTools $orderTools,
        \Psr\Log\LoggerInterface $logger,
        \Loyaltylion\Core\Model\EventQueue $queue
    ) {
        $this->_config = $config;
        $this->_orderTools = $orderTools;
        $this->_logger = $logger;
        $this->_queue = $queue;
    }

    public function execute(Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();
            // We can't track an order without a merchant_id
            if (!$order || !$order->getId()) {
                return;
            }

            // It's important to fetch our credentials with the storeId saved to
            // our order here: it's possible to create orders from different contexts,
            // so the general purpose scopeConfig->getValue would potentially return
            // a different storeId here
            list(, , $orders) = $this->_config->getClientForStore(
                $order->getStoreId()
            );
            if (!$orders) {
                $this->_logger->debug(
                    "[LoyaltyLion] skip order create, disabled"
                );
                // We aren't enabled in the website/store this order was placed in
                return;
            }

            $data = array_merge(
                $this->_orderTools->getBaseOrderData($order),
                $this->_orderTools->getOrderMetadata($order),
                $this->_orderTools->getPaymentStatus($order),
                $this->_orderTools->getOrderClientData($order),
                $this->_orderTools->getDiscountCodes($order)
            );

            // Sent later, so the order keeps the date it was placed on.
            $data["date"] = gmdate("c", strtotime($order->getCreatedAt() . " UTC"));

            // Built now: the shopper's tracking and referral ids live in their session.
            $this->_queue->add(
                $order->getStoreId(),
                \Loyaltylion\Core\Model\Outbox::KIND_ORDER_CREATE,
                "order:" . $order->getId(),
                $data
            );
        } catch (\Exception $e) {
            $this->_logger->error(
                "[LoyaltyLion] Unexpected error: " . $e->getMessage()
            );
        }
    }
}
