<?php
namespace Loyaltylion\Core\Block;

class Sdk extends \Magento\Framework\View\Element\Template {
    private $customerSession;

  const TOKEN = 'loyaltylion_core/general/token';
  const SECRET = 'loyaltylion_core/general/secret';
  const SDK_URL = 'dg1f2pfrgjxdq.cloudfront.net/libs/ll.sdk-1.1.js';
  const PLATFORM_URL = 'platform.loyaltylion.com';

  public function __construct(
      \Magento\Framework\View\Element\Template\Context $context,
      \Magento\Customer\Model\Session $customerSession,
      array $data = []
  ) {
        parent::__construct($context, $data);

        $this->customerSession = $customerSession;
    }

  public function isEnabled() {
    $token = $this->getToken();
    $secret = $this->getSecret();
    if (empty($token) || empty($secret)) return false;
    return true;
  }

  public function getToken() {
    return $this->_scopeConfig->getValue(self::TOKEN,  \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
  }

  public function getSecret() {
    return $this->_scopeConfig->getValue(self::SECRET,  \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
  }

  public function getSDKUrl() {
    return isset($_SERVER['LOYALTYLION_SDK_URL']) ? $_SERVER['LOYALTYLION_SDK_URL'] : self::SDK_URL;
  }

  public function getPlatformHost() {
    return isset($_SERVER['LOYALTYLION_PLATFORM_HOST']) ? $_SERVER['LOYALTYLION_PLATFORM_HOST'] : self::PLATFORM_URL;
  }

  public function isLoggedIn() {
      return $this->customerSession->isLoggedIn();
  }

  public function getCustomer() {
      return $this->customerSession->getCustomer();
  }
}
