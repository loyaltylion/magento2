<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RegisterObserver implements ObserverInterface
{
    private $_config;
    private $_logger;
    private $_tracking;
    private $_queue;

    public function __construct(
        \Loyaltylion\Core\Helper\Config $config,
        \Psr\Log\LoggerInterface $logger,
        \Loyaltylion\Core\Helper\Tracking $tracking,
        \Loyaltylion\Core\Model\EventQueue $queue
    ) {
        $this->_config = $config;
        $this->_logger = $logger;
        $this->_tracking = $tracking;
        $this->_queue = $queue;
    }

    public function execute(Observer $observer)
    {
        try {
            $customer = $observer->getEvent()->getCustomer();
            list(, $events) = $this->_config->getClientForStore(
                $customer->getStoreId()
            );
            if (!$events) {
                // We aren't enabled in the website/store this customer registered at
                return;
            }

            // The shopper's tracking and referral ids live in their session,
            // so the body is built now rather than when the event is sent.
            $this->_queue->add(
                $customer->getStoreId(),
                \Loyaltylion\Core\Model\Outbox::KIND_SIGNUP,
                "customer:" . $customer->getId(),
                array_merge(["name" => '$signup'], $this->_buildPayload($customer))
            );
        } catch (\Exception $e) {
            $this->_logger->error(
                "[LoyaltyLion] Unexpected error: " . $e->getMessage()
            );
        }
    }

    private function _buildPayload($customer)
    {
        $data = [
            "customer_id" => $customer->getId(),
            "customer_email" => $customer->getEmail(),
            "date" => date("c"),
        ];

        return array_merge($data, $this->_tracking->getTrackingData());
    }
}
