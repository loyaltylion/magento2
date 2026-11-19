<?php

namespace Loyaltylion\Core\Model\Observer;

use Loyaltylion\Core\Model\Outbox;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomerEmailChangedObserver implements ObserverInterface
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
            $customer = $observer->getEvent()->getCustomer();
            if (!$customer || !$customer->getId()) {
                return;
            }
            list($connection) = $this->_config->getClientForStore($customer->getStoreId());
            if (!$connection) {
                return;
            }
            $this->_queue->add(
                $customer->getStoreId(),
                Outbox::KIND_CUSTOMER_EMAIL,
                "customer:" . $customer->getId(),
                ["email" => $customer->getEmail()]
            );
        } catch (\Exception $e) {
            $this->_logger->error("[LoyaltyLion] Unexpected error: " . $e->getMessage());
        }
    }
}
