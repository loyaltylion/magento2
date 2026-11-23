<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Block;

use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * The element LoyaltyLion's SDK draws its points earned block into, on the
 * page that confirms an order. It carries the order's entity id, which is the
 * id LoyaltyLion holds the order under.
 */
class OrderConfirmation extends Template
{
    public function __construct(
        Context $context,
        private readonly Placements $placements,
        private readonly Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrderId(): string
    {
        return (string) $this->checkoutSession->getLastOrderId();
    }

    protected function _toHtml()
    {
        return $this->placements->isOn(Placements::ORDER_CONFIRMATION) ? parent::_toHtml() : '';
    }
}
