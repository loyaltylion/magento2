<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class OrderCreateObserver implements ObserverInterface
{
    private $_client;
    private $_config;
    private $_referrals;
    private $_orderTools;
    private $_logger;

    public function __construct(
        \Loyaltylion\Core\Helper\Client $client,
        \Loyaltylion\Core\Helper\Config $config,
        \Loyaltylion\Core\Helper\OrderTools $orderTools,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_client = $client;
        $this->_config = $config;
        $this->_orderTools = $orderTools;
        $this->_logger = $logger;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        // We can't track an order without a merchant_id
        if (!$order || !$order->getId()) {
            return;
        }

        // It's important to fetch our credentials with the storeId saved to
        // our order here: it's possible to create orders from different contexts,
        // so the general purpose scopeConfig->getValue would potentially return
        // a different storeId here
        $creds = $this->_config->getCredentialsForStore($order->getStoreId());
        if (!$this->_config->isEnabled(...$creds)) {
            return;
        }
        list(, , $orders) = $this->_client->getClient(...$creds);

        $data = array_merge(
            $this->_orderTools->getBaseOrderData($order),
            $this->_orderTools->getOrderMetadata($order),
            $this->_orderTools->getPaymentStatus($order),
            $this->_orderTools->getOrderClientData($order),
            $this->_orderTools->getDiscountCodes($order)
        );

        $response = $orders->create($data);

        if ($response->success) {
            $this->_logger->debug('[LoyaltyLion] Tracked order OK');
        } else {
            $this->_logger->error(
                '[LoyaltyLion] Failed to track order - status: ' .
                    $response->status .
                    ', error: ' .
                    $response->error
            );
        }
    }
}
