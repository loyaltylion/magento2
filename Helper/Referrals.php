<?php
/**
 * Created by PhpStorm.
 * User: patrick
 * Date: 15/03/2017
 * Time: 18:02
 */

namespace Loyaltylion\Core\Helper;

class Referrals
{
    private $_session;

    public function __construct(
        \Magento\Customer\Model\Session $session
    ) {
        $this->_session = $session;
    }

    /**
     * Check the session for a `tracking_id`, and return it unless it has expired
     *
     * @return [type] Tracking id or null if it doesn't exist or has expired
     */
    public function getTrackingIdFromSession()
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
        if (time() - (int)$values[0] > 86400) {
            return null;
        }

        return $values[1];
    }

    public function getLoyaltyLionReferralId()
    {
        return $this->_session->getLoyaltyLionReferralId();
    }
}
