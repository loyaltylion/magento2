<?php

namespace Loyaltylion\Core\CustomerData;

class LionCustomer implements
    \Magento\Customer\CustomerData\SectionSourceInterface
{
    private $_session;
    private $_config;

    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Loyaltylion\Core\Helper\Config $config
    ) {
        $this->_session = $customerSession;
        $this->_config = $config;
    }

    public function getSectionData()
    {
        $customer = $this->_session->getCustomer();
        list(, $secret) = $this->_config->getCredentialsForContext();
        $now = date('c');
        return [
            'logged_in' => $this->_session->isLoggedIn(),
            'date' => $now,
            'customer' => [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'name' => $customer->getName(),
            ],
            'auth_token' => sha1($customer->getId() . $now . $secret),
        ];
    }
}
