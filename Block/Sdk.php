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
     * Where the SDK posts a claimed free product's code to have the product
     * added to the cart, or null when the merchant hasn't turned that on
     */
    public function getFreeProductCartUrl()
    {
        return $this->_freeProduct->isOn()
            ? $this->getUrl("loyaltylion/reward/add")
            : null;
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
