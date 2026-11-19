<?php

namespace Loyaltylion\Core\Block\Checkout;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Adds the mount point for redeeming points at checkout beside the discount
 * code form in the payment step, while the setting is on. A checkout that has
 * no such step (a one-step checkout add-on) is left as it is.
 */
class CheckoutRedemptionLayoutProcessor implements LayoutProcessorInterface
{
    const XML_PATH_ENABLED = "loyaltylion_core/checkout_redemption/enabled";

    private $_scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->_scopeConfig = $scopeConfig;
    }

    public function process($jsLayout)
    {
        if (!$this->_scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE)) {
            return $jsLayout;
        }

        $steps = $jsLayout["components"]["checkout"]["children"]["steps"]["children"] ?? [];

        if (!isset($steps["billing-step"]["children"]["payment"]["children"]["afterMethods"]["children"])) {
            return $jsLayout;
        }

        $jsLayout["components"]["checkout"]["children"]["steps"]["children"]["billing-step"]["children"]
            ["payment"]["children"]["afterMethods"]["children"]["loyaltylion-checkout-redemption"] = [
                "component" => "Loyaltylion_Core/js/view/checkout-redemption",
                "sortOrder" => 0,
            ];

        return $jsLayout;
    }
}
