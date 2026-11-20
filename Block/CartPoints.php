<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Block;

use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/** The points a shopper earns for what is in their cart, on the cart page. */
class CartPoints extends Template
{
    public function __construct(
        Context $context,
        private readonly Session $checkoutSession,
        private readonly Placements $placements,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isOn(): bool
    {
        return $this->placements->isOn(Placements::CART_POINTS);
    }

    /**
     * The cart's subtotal after discounts in minor units of the cart's
     * currency, which is what the SDK's points-for element takes. Shipping and
     * tax aren't known on the cart page, so they aren't included. Zero when
     * there is nothing to show.
     */
    public function getSubtotalInMinorUnits(): int
    {
        if (!$this->isOn()) {
            return 0;
        }

        return (int) round(((float) $this->checkoutSession->getQuote()->getSubtotalWithDiscount()) * 100);
    }
}
