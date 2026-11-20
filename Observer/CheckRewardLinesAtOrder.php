<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Observer;

use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

class CheckRewardLinesAtOrder implements ObserverInterface
{
    public function __construct(private readonly FreeProduct $freeProduct)
    {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();

        if ($this->freeProduct->removeRefusedLines($quote)) {
            throw new LocalizedException(
                __('A reward in your cart is no longer available, so we removed it. Check your order and place it again.')
            );
        }

        $this->freeProduct->labelOrderLines($observer->getEvent()->getOrder(), $quote);
    }
}
