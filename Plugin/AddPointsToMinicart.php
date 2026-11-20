<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Plugin;

use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Checkout\Block\Cart\Sidebar;
use Magento\Framework\Serialize\Serializer\Json;

/** Adds the points for the cart to Luma's mini cart, under its subtotal. */
class AddPointsToMinicart
{
    public function __construct(
        private readonly Placements $placements,
        private readonly Json $serializer
    ) {
    }

    /**
     * @param string $jsLayout
     * @return string
     */
    public function afterGetJsLayout(Sidebar $subject, $jsLayout)
    {
        if (!$this->placements->isOn(Placements::CART_POINTS)) {
            return $jsLayout;
        }

        $layout = $this->serializer->unserialize($jsLayout);
        if (!isset($layout['components']['minicart_content']['children']['extra_info'])) {
            return $jsLayout;
        }

        $layout['components']['minicart_content']['children']['extra_info']['children']['loyaltylion_points'] = [
            'component' => 'Loyaltylion_Core/js/view/minicart-points',
        ];

        return $this->serializer->serialize($layout);
    }
}
