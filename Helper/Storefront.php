<?php

namespace Loyaltylion\Core\Helper;

use Magento\Store\Model\ScopeInterface;

/**
 * The storefront placements a merchant turns on under Stores > Configuration >
 * Customers > LoyaltyLion > Storefront. Every one is off until the merchant
 * turns it on, so upgrading the module changes nothing a shopper sees.
 */
class Storefront
{
    const LOYALTY_PAGE = "loyaltylion_core/storefront/loyalty_page";
    const LOYALTY_PAGE_URL_KEY = "loyaltylion_core/storefront/loyalty_page_url_key";
    const LOYALTY_PAGE_ELEMENT = "loyaltylion_core/storefront/loyalty_page_element";
    const MENU_LINK = "loyaltylion_core/storefront/menu_link";
    const ACCOUNT_PAGE = "loyaltylion_core/storefront/account_page";
    const POST_PURCHASE = "loyaltylion_core/storefront/post_purchase";
    const STORE_LANGUAGE = "loyaltylion_core/storefront/store_language";

    const DEFAULT_URL_KEY = "loyalty";

    /**
     * The loyalty page's element and its attribute value, by the setting's value
     */
    const PAGE_ELEMENTS = [
        "integrated-page" => ["data-lion-integrated-page", ""],
        "account" => ["data-lion-account", ""],
    ];

    private $_scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->_scopeConfig = $scopeConfig;
    }

    public function isOn($path)
    {
        return $this->_scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE);
    }

    public function isLoyaltyPageOn()
    {
        return $this->isOn(self::LOYALTY_PAGE);
    }

    public function getLoyaltyPageUrlKey()
    {
        $key = trim(
            (string) $this->_scopeConfig->getValue(
                self::LOYALTY_PAGE_URL_KEY,
                ScopeInterface::SCOPE_STORE
            ),
            "/ "
        );

        return $key === "" ? self::DEFAULT_URL_KEY : $key;
    }

    /**
     * The attribute that places the loyalty page, and its value
     */
    public function getLoyaltyPageElement()
    {
        $value = (string) $this->_scopeConfig->getValue(
            self::LOYALTY_PAGE_ELEMENT,
            ScopeInterface::SCOPE_STORE
        );

        return self::PAGE_ELEMENTS[$value] ?? self::PAGE_ELEMENTS["integrated-page"];
    }
}
