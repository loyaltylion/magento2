<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Controller\Account;

use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Customer\Controller\AbstractAccount;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * The Rewards page in the customer's account. Extending AbstractAccount sends
 * a shopper who isn't signed in to the sign-in page first.
 */
class Index extends AbstractAccount implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly Placements $placements
    ) {
        parent::__construct($context);
    }

    /**
     * No return type: for a guest, Magento's account plugin answers in place of this with a redirect to sign-in.
     *
     * @return ResultInterface
     */
    public function execute()
    {
        if (!$this->placements->isOn(Placements::ACCOUNT_PAGE)) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Rewards'));

        return $page;
    }
}
