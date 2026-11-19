<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class FreeProductPlacedObserver implements ObserverInterface
{
    private $_freeProduct;

    public function __construct(
        \Loyaltylion\Core\Model\Reward\FreeProduct $freeProduct
    ) {
        $this->_freeProduct = $freeProduct;
    }

    public function execute(Observer $observer)
    {
        $this->_freeProduct->retireCoupons($observer->getEvent()->getOrder());
    }
}
