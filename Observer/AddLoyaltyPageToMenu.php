<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Observer;

use Loyaltylion\Core\Model\Storefront\MenuLink;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/** Adds the loyalty page to the main menu of Luma and the themes built on it. */
class AddLoyaltyPageToMenu implements ObserverInterface
{
    public function __construct(private readonly MenuLink $menuLink)
    {
    }

    public function execute(Observer $observer): void
    {
        $this->menuLink->addTo($observer->getData('menu'));
    }
}
