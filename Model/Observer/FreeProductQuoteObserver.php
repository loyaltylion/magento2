<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Keeps a free product line at one unit and no charge whenever the cart's
 * totals are worked out, whatever the shopper changed on it.
 */
class FreeProductQuoteObserver implements ObserverInterface
{
    private $_freeProduct;

    public function __construct(
        \Loyaltylion\Core\Model\Reward\FreeProduct $freeProduct
    ) {
        $this->_freeProduct = $freeProduct;
    }

    public function execute(Observer $observer)
    {
        $this->_freeProduct->enforce($observer->getEvent()->getQuote());
    }
}
