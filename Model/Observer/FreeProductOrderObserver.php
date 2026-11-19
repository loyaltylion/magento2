<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Before an order is placed, checks with LoyaltyLion that each free product in
 * the cart is still a claim the shopper holds. A claim refunded or used since
 * the line was added would otherwise give the product away for nothing. Such
 * a line is taken out of the cart and the order is stopped, because the
 * shopper has not seen the cart without it.
 */
class FreeProductOrderObserver implements ObserverInterface
{
    private $_freeProduct;

    public function __construct(
        \Loyaltylion\Core\Model\Reward\FreeProduct $freeProduct
    ) {
        $this->_freeProduct = $freeProduct;
    }

    public function execute(Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();

        if ($this->_freeProduct->removeRefusedLines($quote)) {
            throw new LocalizedException(
                __(
                    "A reward in your cart is no longer available, so we removed it. Check your order and place it again."
                )
            );
        }

        $this->_freeProduct->labelOrderLines(
            $observer->getEvent()->getOrder(),
            $quote
        );
    }
}
