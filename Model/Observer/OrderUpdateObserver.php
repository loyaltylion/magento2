<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class OrderUpdateObserver implements ObserverInterface
{
    private $_config;
    private $_orderTools;
    private $_logger;

    public function __construct(
        \Loyaltylion\Core\Helper\Config $config,
        \Loyaltylion\Core\Helper\OrderTools $orderTools,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_config = $config;
        $this->_orderTools = $orderTools;
        $this->_logger = $logger;
    }

    public function execute(Observer $observer)
    {
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

        $data = array_merge(
            $this->_orderTools->getOrderClientData($order),
            $this->_orderTools->getPaymentStatus($order),
            $this->_orderTools->getCancellationStatus($order),
            $this->_orderTools->getOrderMetadata($order),
            $this->_orderTools->getRefundStatus($order)
        );

        $response = $orders->update($order->getId(), $data);

        if ($response->success) {
            $this->_logger->debug('[LoyaltyLion] Updated order OK');
        } elseif ($response->status != 404) {
            // sometimes this will get fired before the order has been created,
            // so we'll get a 404 back - no reason to error, because this is expected
            $this->_logger->error(
                '[LoyaltyLion] Failed to update order - status: ' .
                    $response->status .
                    ', error: ' .
                    $response->error
            );
        }
    }
}
