<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RegisterObserver implements ObserverInterface
{
    private $_client, $_config, $_logger, $_referrals;

    public function __construct(
        \Loyaltylion\Core\Helper\Client $client,
        \Loyaltylion\Core\Helper\Config $config,
        \Psr\Log\LoggerInterface $logger,
        \Loyaltylion\Core\Helper\Referrals $referrals
    )
    {
        $this->_client = $client;
        $this->_config = $config;
        $this->_logger = $logger;
        $this->_referrals = $referrals;
    }

    public function execute(Observer $observer)
    {
        if (!$this->_config->isEnabled()) return;
        $customer = $observer->getEvent()->getCustomer();
        $this->trackSignup($customer);
    }

    /**
     * Track a signup event for the given customer
     *
     * @param  [type] $customer [description]
     * @return [type]           [description]
     */
    private function trackSignup($customer)
    {

        $data = array(
            'customer_id' => $customer->getId(),
            'customer_email' => $customer->getEmail(),
            'date' => date('c'),
            // TODO: check REMOTE_ADDR is actually the thing we want and
            // not just the reverse proxy's IP
            // also, this'll probably fail validation...
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        );

        //TODO: do we actually use this anymore?
        if ($this->_referrals->getLoyaltyLionReferralId())
            $data['referral_id'] = $this->_referrals->getLoyaltyLionReferralId();

        //TODO: also check that this works
        $tracking_id = $this->getTrackingIdFromSession();

        if ($tracking_id)
            $data['tracking_id'] = $tracking_id;

        $response = $this->_client->events->track('$signup', $data);

        if ($response->success) {
            $this->_logger->addDebug('[LoyaltyLion] Tracked event [signup] OK');
        } else {
            $this->_logger->addDebug(
                '[LoyaltyLion] Failed to track event - status: ' . $response->status . ', error: ' . $response->error);
        }
    }
}
