<?php

namespace Loyaltylion\Core\Block;

class Sdk extends \Magento\Framework\View\Element\Template
{
    private $_config;
    private $_freeProduct;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Loyaltylion\Core\Helper\Config $config,
        \Loyaltylion\Core\Model\Reward\FreeProduct $freeProduct,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_config = $config;
        $this->_freeProduct = $freeProduct;
    }

    /**
     * What the storefront script needs to put a claimed free product in the
     * cart, as JSON, or null when the merchant hasn't turned that on
     */
    public function getFreeProductCartConfig()
    {
        if (!$this->_freeProduct->isOn()) {
            return null;
        }

        return json_encode([
            "url" => $this->getUrl("loyaltylion/reward/add"),
            "message" => (string) __("Your reward is in your cart."),
        ]);
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
