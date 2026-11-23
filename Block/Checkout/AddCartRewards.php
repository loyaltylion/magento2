<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Block\Checkout;

use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;

/**
 * Adds the element LoyaltyLion's SDK draws its Cart rewards block into, beside
 * the discount code form in the payment step. A checkout that has no such step
 * (a one-step checkout add-on) is left as it is.
 */
class AddCartRewards implements LayoutProcessorInterface
{
    public function __construct(private readonly Placements $placements)
    {
    }

    /**
     * @param array $jsLayout
     * @return array
     */
    public function process($jsLayout)
    {
        if (!$this->placements->isOn(Placements::CHECKOUT_CART_REWARDS)) {
            return $jsLayout;
        }

        $steps = $jsLayout['components']['checkout']['children']['steps']['children'] ?? [];
        if (!isset($steps['billing-step']['children']['payment']['children']['afterMethods']['children'])) {
            return $jsLayout;
        }

        $billing = &$jsLayout['components']['checkout']['children']['steps']['children']['billing-step'];
        $billing['children']['payment']['children']['afterMethods']['children']['loyaltylion-cart-rewards'] = [
            'component' => 'Loyaltylion_Core/js/view/cart-rewards',
            'sortOrder' => 0,
        ];

        return $jsLayout;
    }
}
