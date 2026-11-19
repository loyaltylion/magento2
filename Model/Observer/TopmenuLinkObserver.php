<?php

namespace Loyaltylion\Core\Model\Observer;

/**
 * Adds the loyalty page to the storefront's main menu, after the categories
 */
class TopmenuLinkObserver implements \Magento\Framework\Event\ObserverInterface
{
    private $_storefront;
    private $_urlBuilder;

    public function __construct(
        \Loyaltylion\Core\Helper\Storefront $storefront,
        \Magento\Framework\UrlInterface $urlBuilder
    ) {
        $this->_storefront = $storefront;
        $this->_urlBuilder = $urlBuilder;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (
            !$this->_storefront->isLoyaltyPageOn() ||
            !$this->_storefront->isOn(\Loyaltylion\Core\Helper\Storefront::MENU_LINK)
        ) {
            return;
        }

        /** @var \Magento\Framework\Data\Tree\Node $menu */
        $menu = $observer->getData("menu");
        $menu->addChild(
            new \Magento\Framework\Data\Tree\Node(
                [
                    "name" => __("Rewards"),
                    "id" => "loyaltylion-loyalty-page",
                    "url" => $this->_urlBuilder->getDirectUrl(
                        $this->_storefront->getLoyaltyPageUrlKey()
                    ),
                    "has_active" => false,
                    "is_active" => false,
                ],
                "id",
                $menu->getTree(),
                $menu
            )
        );
    }
}
