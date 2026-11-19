<?php

namespace Loyaltylion\Core\Controller\Index;

class Index implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    private $_pageFactory;
    private $_forwardFactory;
    private $_storefront;

    public function __construct(
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\Controller\Result\ForwardFactory $forwardFactory,
        \Loyaltylion\Core\Helper\Storefront $storefront
    ) {
        $this->_pageFactory = $pageFactory;
        $this->_forwardFactory = $forwardFactory;
        $this->_storefront = $storefront;
    }

    public function execute()
    {
        if (!$this->_storefront->isLoyaltyPageOn()) {
            return $this->_forwardFactory->create()->forward("noroute");
        }

        $page = $this->_pageFactory->create();
        $page->getConfig()->getTitle()->set(__("Rewards"));

        return $page;
    }
}
