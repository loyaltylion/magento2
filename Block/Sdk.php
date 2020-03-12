<?php

namespace Loyaltylion\Core\Block;

class Sdk extends \Magento\Framework\View\Element\Template
{
    private $_config;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Loyaltylion\Core\Helper\Config $config,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->_config = $config;
    }

    public function isEnabledInContext()
    {
        return $this->_config->isEnabledInContext();
    }

    public function getToken()
    {
        return $this->_config->getToken();
    }

    public function getSecret()
    {
        return $this->_config->getSecret();
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
