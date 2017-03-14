<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Core\Model\ObjectManager;

class RegisterObserver implements ObserverInterface {
  public function __construct(
    \Loyaltylion\Core\Helper\Client $client,
    \Loyaltylion\Core\Block\Sdk $sdk,
    \Psr\Log\LoggerInterface $logger,
    \Magento\Customer\Model\Session $session
  ) {
    $this->_client = $client;
    $this->_sdk = $sdk;
    $this->_logger = $logger;       
    $this->_session = $session;
  }

  public function execute(Observer $observer) {
    if (!$this->_sdk->isEnabled()) return;
    $customer = $observer->getEvent()->getCustomer();
    $this->trackSignup($customer);
  }

  /**
   * Track a signup event for the given customer
   *
   * @param  [type] $customer [description]
   * @return [type]           [description]
   */
  private function trackSignup($customer) {

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
    if ($this->_session->getLoyaltyLionReferralId())
      $data['referral_id'] = $this->_session->getLoyaltyLionReferralId();

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

  /**
   * Check the session for a `tracking_id`, and return it unless it has expired
   *
   * @return [type] Tracking id or null if it doesn't exist or has expired
   */
  private function getTrackingIdFromSession() {
    if (!$this->_session->getLoyaltyLionTrackingId())
      return null;

    $values = explode(':::', $this->_session->getLoyaltyLionTrackingId());

    if (empty($values))
      return null;

    if (count($values) != 2)
      return $values[0];

    // for now, let's have a 24 hour expiration time on the timestamp
    if (time() - (int)$values[0] > 86400)
      return null;

    return $values[1];
  }
}
