<?php

namespace Loyaltylion\Core\Block;

class CartPoints extends \Magento\Framework\View\Element\Template
{
    private $_checkoutSession;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_checkoutSession = $checkoutSession;
    }

    /**
     * The cart's subtotal after discounts in minor units of the cart's
     * currency, which is what the SDK's points-for element takes. Shipping and
     * tax aren't known on the cart page, so they aren't included.
     */
    public function getSubtotalInMinorUnits()
    {
        $quote = $this->_checkoutSession->getQuote();

        return (int) round(((float) $quote->getSubtotalWithDiscount()) * 100);
    }
}
