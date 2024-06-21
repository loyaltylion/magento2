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
    const TOKEN = "loyaltylion_core/general/token";
    const SECRET = "loyaltylion_core/general/secret";
    const LOADER_PATH = "/static/2/loader.js";
    const SDK_HOST = "sdk.loyaltylion.net";
    const API_HOST = "api.loyaltylion.com";

    private $_scopeConfig;
    private $_request;
    private $_client;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\HTTP\PhpEnvironment\Request $request,
        \Loyaltylion\Core\Helper\Client $client
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_request = $request;
        $this->_client = $client;
    }

    public function isEnabled($token, $secret)
    {
        return !(empty($token) || empty($secret));
    }

    /**
     * This should be your default option for most cases:
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
        $path = getenv("LOYALTYLION_LOADER_PATH") ?: self::LOADER_PATH;
        return $this->getSdkHost() . $path;
    }

    public function getSdkHost()
    {
        return getenv("LOYALTYLION_SDK_HOST") ?: self::SDK_HOST;
    }

    public function getClientForStore($storeId)
    {
        $creds = $this->getCredentialsForStore($storeId);
        if (!$this->isEnabled(...$creds)) {
            return [null, null, null];
        }
        return $this->_client->getClient($this->_getApiBaseUrl(), ...$creds);
    }

    private function _getApiHost()
    {
        return getenv("LOYALTYLION_API_HOST") ?: self::API_HOST;
    }

    private function _getApiBaseUrl()
    {
        return "https://" . $this->_getApiHost() . "/v2";
    }
}
