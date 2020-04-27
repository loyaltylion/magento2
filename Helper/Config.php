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
    ) {
        $this->_scopeConfig = $scopeConfig;
    }

    public function isEnabled($token, $secret)
    {
        return !(empty($token) || empty($secret));
    }

    /**
     * getCredentialsForStore should be your default option for most cases:
     * while the current store can be inferred from context in some situations,
     * it may be defaulted and implicitly fallback to something else in others.
     * For example, within the admin view, this falls back to the first store.
     *
     * Therefore, for safety, it's best to always be explicit about which store
     * you're inspecting the config of with this function.
     */
    public function getCredentialsForStore($storeId)
    {
        $token = $this->_scopeConfig->getValue(
            self::TOKEN,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $secret = $this->_scopeConfig->getValue(
            self::SECRET,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return [$token, $secret];
    }

    /**
     * If you can trust the context to correctly know the current store
     * (e.g. only in the non-admin frontend), you can use this to find
     * the LL credentials for the current context.
     */
    public function getCredentialsForContext()
    {
        $token = $this->_scopeConfig->getValue(
            self::TOKEN,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $secret = $this->_scopeConfig->getValue(
            self::SECRET,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return [$token, $secret];
    }

    public function getLoaderUrl()
    {
        $path = isset($_SERVER['LOYALTYLION_LOADER_PATH'])
            ? $_SERVER['LOYALTYLION_LOADER_PATH']
            : self::LOADER_PATH;
        return $this->getSdkHost() . $path;
    }

    public function getSdkHost()
    {
        return isset($_SERVER['LOYALTYLION_SDK_HOST'])
            ? $_SERVER['LOYALTYLION_SDK_HOST']
            : self::SDK_HOST;
    }
}
