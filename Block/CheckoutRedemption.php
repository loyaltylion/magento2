<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Block;

use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/** The cart page's element for redeeming points, shown while the merchant has it on. */
class CheckoutRedemption extends Template
{
    public function __construct(
        Context $context,
        private readonly Placements $placements,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml()
    {
        return $this->placements->isOn(Placements::CHECKOUT_REDEMPTION) ? parent::_toHtml() : '';
    }
}
