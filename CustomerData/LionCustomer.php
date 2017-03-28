<?php

namespace Loyaltylion\Core\CustomerData;

class LionCustomer implements \Magento\Customer\CustomerData\SectionSourceInterface
{
    private $session;
    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Loyaltylion\Core\Helper\Config $config
) {
        $this->session = $customerSession;
        $this->config = $config;
    }

    public function getSectionData() {
        $customer = $this->session->getCustomer();
        $now = date('c');
        return (
            [
                'logged_in' => $this->session->isLoggedIn(),
                'date' => $now,
                'customer' => [
                    'id' => $customer->getId(),
                    'email' => $customer->getEmail(),
                    'name' => $customer->getName()
                    ],
                'auth_token' => sha1($customer->getId() . $now . $this->config->getSecret()),
            ]
        );
    }
}