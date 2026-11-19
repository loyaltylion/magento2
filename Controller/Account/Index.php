<?php

namespace Loyaltylion\Core\Controller\Account;

/**
 * The Rewards page in the customer account. Extending AbstractAccount sends a
 * shopper who isn't signed in to the sign-in page first.
 */
class Index extends \Magento\Customer\Controller\AbstractAccount implements
    \Magento\Framework\App\Action\HttpGetActionInterface
{
    private $_pageFactory;
    private $_forwardFactory;
    private $_storefront;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\Controller\Result\ForwardFactory $forwardFactory,
        \Loyaltylion\Core\Helper\Storefront $storefront
    ) {
        parent::__construct($context);
        $this->_pageFactory = $pageFactory;
        $this->_forwardFactory = $forwardFactory;
        $this->_storefront = $storefront;
    }

    public function execute()
    {
        if (!$this->_storefront->isOn(\Loyaltylion\Core\Helper\Storefront::ACCOUNT_PAGE)) {
            return $this->_forwardFactory->create()->forward("noroute");
        }

        $page = $this->_pageFactory->create();
        $page->getConfig()->getTitle()->set(__("Rewards"));

        return $page;
    }
}
