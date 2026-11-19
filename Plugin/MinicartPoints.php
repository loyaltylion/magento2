<?php

namespace Loyaltylion\Core\Plugin;

/**
 * Adds the points for the cart to the mini cart, under its subtotal, while
 * the merchant has it turned on
 */
class MinicartPoints
{
    private $_storefront;
    private $_serializer;

    public function __construct(
        \Loyaltylion\Core\Helper\Storefront $storefront,
        \Magento\Framework\Serialize\Serializer\Json $serializer
    ) {
        $this->_storefront = $storefront;
        $this->_serializer = $serializer;
    }

    public function afterGetJsLayout(\Magento\Checkout\Block\Cart\Sidebar $subject, $jsLayout)
    {
        if (!$this->_storefront->isOn(\Loyaltylion\Core\Helper\Storefront::MINICART_POINTS)) {
            return $jsLayout;
        }

        $layout = $this->_serializer->unserialize($jsLayout);

        if (!isset($layout["components"]["minicart_content"]["children"]["extra_info"])) {
            return $jsLayout;
        }

        $layout["components"]["minicart_content"]["children"]["extra_info"]["children"]["loyaltylion_points"] = [
            "component" => "Loyaltylion_Core/js/view/minicart-points",
        ];

        return $this->_serializer->serialize($layout);
    }
}
