<?php

namespace Loyaltylion\Core\Block;

/**
 * The element the SDK renders the loyalty page into, as the merchant chose it
 */
class LoyaltyPage extends \Magento\Framework\View\Element\Template
{
    private $_storefront;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Loyaltylion\Core\Helper\Storefront $storefront,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_storefront = $storefront;
    }

    public function getElement()
    {
        return $this->_storefront->getLoyaltyPageElement();
    }
}
