<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Observer;

use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Framework\Data\Tree\Node;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;

/** Adds the loyalty page to the storefront's main menu, after the categories. */
class AddLoyaltyPageToMenu implements ObserverInterface
{
    public function __construct(
        private readonly Placements $placements,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->placements->isOn(Placements::LOYALTY_PAGE) || !$this->placements->isOn(Placements::MENU_LINK)) {
            return;
        }

        /** @var Node $menu */
        $menu = $observer->getData('menu');
        $menu->addChild(new Node(
            [
                'name' => __('Rewards'),
                'id' => 'loyaltylion-loyalty-page',
                'url' => $this->urlBuilder->getDirectUrl($this->placements->loyaltyPageUrlKey()),
                'has_active' => false,
                'is_active' => false,
            ],
            'id',
            $menu->getTree(),
            $menu
        ));
    }
}
