<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Storefront;

use Magento\Framework\Data\Tree\Node;
use Magento\Framework\UrlInterface;

/**
 * The loyalty page's link in the storefront's main menu, after the categories,
 * on a website that shows the page and has the link turned on.
 */
class MenuLink
{
    public function __construct(
        private readonly Placements $placements,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Adds the link to the root of a menu built from Magento's category tree.
     *
     * @param Node $menu
     * @return void
     */
    public function addTo(Node $menu): void
    {
        if (!$this->placements->isOn(Placements::LOYALTY_PAGE) || !$this->placements->isOn(Placements::MENU_LINK)) {
            return;
        }

        // Adding it again, as a theme that builds its menu more than once per
        // page does, replaces the link: a menu holds one node per id.
        $menu->addChild(new Node(
            [
                'name' => __('Rewards'),
                'id' => 'loyaltylion-loyalty-page',
                'url' => $this->urlBuilder->getDirectUrl($this->placements->loyaltyPageUrlKey()),
                'has_active' => false,
                'is_active' => false,
                'is_category' => false,
            ],
            'id',
            $menu->getTree(),
            $menu
        ));
    }
}
