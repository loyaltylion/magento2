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
