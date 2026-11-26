<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Plugin;

use Loyaltylion\Core\Model\Storefront\MenuLink;
use Magento\Framework\Data\Tree\Node;

/**
 * Adds the loyalty page to the main menu of Hyvä and the themes built on it,
 * which build their menu with Hyvä's navigation service rather than Magento's
 * Topmenu block. It is declared on a class only Hyvä has, so on a store
 * without Hyvä it is never called.
 */
class AddLoyaltyPageToHyvaMenu
{
    public function __construct(private readonly MenuLink $menuLink)
    {
    }

    /**
     * @param object $subject Hyvä's navigation service, untyped because Hyvä is not a dependency of this module
     * @param Node $menu
     * @return Node
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetMenuTree($subject, Node $menu): Node
    {
        $this->menuLink->addTo($menu);

        return $menu;
    }
}
