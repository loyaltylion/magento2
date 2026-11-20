<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Block\Checkout;

use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;

/**
 * Adds the element LoyaltyLion's SDK draws "redeem points at checkout" into,
 * beside the discount code form in the payment step. A checkout that has no
 * such step (a one-step checkout add-on) is left as it is.
 */
class AddCheckoutRedemption implements LayoutProcessorInterface
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
        if (!$this->placements->isOn(Placements::CHECKOUT_REDEMPTION)) {
            return $jsLayout;
        }

        $steps = $jsLayout['components']['checkout']['children']['steps']['children'] ?? [];
        if (!isset($steps['billing-step']['children']['payment']['children']['afterMethods']['children'])) {
            return $jsLayout;
        }

        $jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']
            ['payment']['children']['afterMethods']['children']['loyaltylion-checkout-redemption'] = [
                'component' => 'Loyaltylion_Core/js/view/checkout-redemption',
                'sortOrder' => 0,
            ];

        return $jsLayout;
    }
}
