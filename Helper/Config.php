<?php
/**
 * Created by PhpStorm.
 * User: patrick
 * Date: 27/03/2017
 * Time: 20:42
 */

namespace Loyaltylion\Core\Helper;


class Config
{
    const TOKEN = 'loyaltylion_core/general/token';
    const SECRET = 'loyaltylion_core/general/secret';
    const LOADER_PATH = '/static/2/loader.js';
    const SDK_HOST = 'sdk.loyaltylion.net';

    private $_scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->_scopeConfig = $scopeConfig;
    }

    public function isEnabledInContext()
    {
        $this->token = $this->_scopeConfig->getValue(self::TOKEN, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->secret = $this->_scopeConfig->getValue(self::SECRET, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if (empty($this->token) || empty($this->secret)) return false;
        return true;
    }

    public function isEnabledForStore($storeId)
    {
        $this->token = $this->_scopeConfig->getValue(self::TOKEN, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        $this->secret = $this->_scopeConfig->getValue(self::SECRET, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);

        if (empty($this->token) || empty($this->secret)) return false;
        return true;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getSecret()
    {
        return $this->secret;
    }

    public function getLoaderUrl()
    {
	$path = isset($_SERVER['LOYALTYLION_LOADER_PATH']) ? $_SERVER['LOYALTYLION_LOADER_PATH'] : self::LOADER_PATH;
        return $this->getSdkHost() . $path;
    }

    public function getSdkHost()
    {
        return isset($_SERVER['LOYALTYLION_SDK_HOST']) ? $_SERVER['LOYALTYLION_SDK_HOST'] : self::SDK_HOST;
    }
}
