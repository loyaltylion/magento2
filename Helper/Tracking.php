<?php
/**
 * Created by PhpStorm.
 * User: patrick
 * Date: 15/03/2017
 * Time: 18:02
 */

namespace Loyaltylion\Core\Helper;

class Tracking
{
    private $_session;
    private $_httpHeader;
    private $_remoteAddress;

    public function __construct(
        \Magento\Customer\Model\Session $session,
        \Magento\Framework\HTTP\Header $httpHeader,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
    ) {
        $this->_session = $session;
        $this->_httpHeader = $httpHeader;
        $this->_remoteAddress = $remoteAddress;
    }

    public function getTrackingData()
    {
        $data = [
            'ip_address' => $this->_remoteAddress->getRemoteAddress(),
            'user_agent' => $this->_httpHeader->getHttpUserAgent(),
        ];

        $referralId = $this->_getLoyaltyLionReferralId();
        if ($referralId) {
            $data['referral_id'] = $referralId;
        }

        $tracking_id = $this->_getTrackingIdFromSession();
        if ($tracking_id) {
            $data['tracking_id'] = $tracking_id;
        }

        return $data;
    }

    /**
     * Check the session for a `tracking_id`, and return it unless it has expired
     *
     * @return [type] Tracking id or null if it doesn't exist or has expired
     */
    private function _getTrackingIdFromSession()
    {
        if (!$this->_session->getLoyaltyLionTrackingId()) {
            return null;
        }

        $values = explode(':::', $this->_session->getLoyaltyLionTrackingId());

        if (empty($values)) {
            return null;
        }

        if (count($values) != 2) {
            return $values[0];
        }

        // for now, let's have a 24 hour expiration time on the timestamp
        if (time() - (int) $values[0] > 86400) {
            return null;
        }

        return $values[1];
    }

    private function _getLoyaltyLionReferralId()
    {
        return $this->_session->getLoyaltyLionReferralId();
    }
}
