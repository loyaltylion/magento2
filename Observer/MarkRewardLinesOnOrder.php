<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Observer;

use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class MarkRewardLinesOnOrder implements ObserverInterface
{
    public function __construct(private readonly FreeProduct $freeProduct)
    {
    }

    public function execute(Observer $observer): void
    {
        $this->freeProduct->markOrderLines($observer->getEvent()->getOrder(), $observer->getEvent()->getQuote());
    }
}
