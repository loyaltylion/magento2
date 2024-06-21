<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RegisterObserver implements ObserverInterface
{
    private $_config;
    private $_logger;
    private $_tracking;

    public function __construct(
        \Loyaltylion\Core\Helper\Config $config,
        \Psr\Log\LoggerInterface $logger,
        \Loyaltylion\Core\Helper\Tracking $tracking
    ) {
        $this->_config = $config;
        $this->_logger = $logger;
        $this->_tracking = $tracking;
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

            $response = $events->track(
                '$signup',
                $this->_buildPayload($customer)
            );

            if ($response->success) {
                $this->_logger->debug(
                    "[LoyaltyLion] Tracked event [signup] OK"
                );
            } else {
                $this->_logger->debug(
                    "[LoyaltyLion] Failed to track event - status: " .
                        $response->status .
                        ", error: " .
                        $response->error
                );
            }
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
