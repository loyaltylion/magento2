<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RegisterObserver implements ObserverInterface
{
    private $_client;
    private $_config;
    private $_logger;
    private $_referrals;

    public function __construct(
        \Loyaltylion\Core\Helper\Client $client,
        \Loyaltylion\Core\Helper\Config $config,
        \Psr\Log\LoggerInterface $logger,
        \Loyaltylion\Core\Helper\Referrals $referrals
    ) {
        $this->_client = $client;
        $this->_config = $config;
        $this->_logger = $logger;
        $this->_referrals = $referrals;
    }

    public function execute(Observer $observer)
    {
        try {
            $customer = $observer->getEvent()->getCustomer();
            $creds = $this->_config->getCredentialsForStore(
                $customer->getStoreId()
            );
            if (!$this->_config->isEnabled(...$creds)) {
                return;
            }
            list(, $events) = $this->_client->getClient(...$creds);

            $data = [
                'customer_id' => $customer->getId(),
                'customer_email' => $customer->getEmail(),
                'date' => date('c'),
                // TODO: Check real IP header - this assumes no reverse proxy
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])
                    ? $_SERVER['HTTP_USER_AGENT']
                    : '',
            ];

            if ($this->_referrals->getLoyaltyLionReferralId()) {
                $data[
                    'referral_id'
                ] = $this->_referrals->getLoyaltyLionReferralId();
            }

            $tracking_id = $this->_referrals->getTrackingIdFromSession();

            if ($tracking_id) {
                $data['tracking_id'] = $tracking_id;
            }

            $response = $events->track('$signup', $data);

            if ($response->success) {
                $this->_logger->debug(
                    '[LoyaltyLion] Tracked event [signup] OK'
                );
            } else {
                $this->_logger->debug(
                    '[LoyaltyLion] Failed to track event - status: ' .
                        $response->status .
                        ', error: ' .
                        $response->error
                );
            }
        } catch (\Exception $e) {
            $this->_logger->error(
                '[LoyaltyLion] Unexpected error: ' . $e->getMessage()
            );
        }
    }
}
