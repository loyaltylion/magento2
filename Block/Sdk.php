<?php

namespace Loyaltylion\Core\Block;

class Sdk extends \Magento\Framework\View\Element\Template
{
    /**
     * The pages a shopper lands on after placing an order
     */
    const ORDER_CONFIRMATION_PAGES = ["checkout_onepage_success", "multishipping_checkout_success"];

    private $_config;
    private $_storefront;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Loyaltylion\Core\Helper\Config $config,
        \Loyaltylion\Core\Helper\Storefront $storefront,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_config = $config;
        $this->_storefront = $storefront;
    }

    /**
     * Options for the SDK's `configure`, from the merchant's storefront settings
     */
    public function getClientConfiguration()
    {
        $configuration = [];

        if (
            $this->_storefront->isOn(\Loyaltylion\Core\Helper\Storefront::POST_PURCHASE) &&
            in_array($this->getRequest()->getFullActionName(), self::ORDER_CONFIRMATION_PAGES, true)
        ) {
            $configuration["orderConfirmationPage"] = true;
        }

        return $configuration;
    }

    public function isEnabled($token, $secret)
    {
        return $this->_config->isEnabled($token, $secret);
    }

    public function getCredentialsForContext()
    {
        return $this->_config->getCredentialsForContext();
    }

    public function getLoaderUrl()
    {
        return $this->_config->getLoaderUrl();
    }

    public function getSdkHost()
    {
        return $this->_config->getSdkHost();
    }
}
