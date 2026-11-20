<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Plugin;

use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session;

/**
 * Gives the mini cart the amount an order of this cart earns points on: the
 * subtotal after discounts. Magento's own mini cart data carries only the
 * subtotal before them, which would promise points a discounted order won't earn.
 */
class AddPointsAmountToCartData
{
    public function __construct(private readonly Session $checkoutSession)
    {
    }

    public function afterGetSectionData(Cart $subject, array $data): array
    {
        $data['loyaltylion_points_amount'] = (int) round(
            ((float) $this->checkoutSession->getQuote()->getSubtotalWithDiscount()) * 100
        );

        return $data;
    }
}
