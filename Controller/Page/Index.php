<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Controller\Page;

use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/** The loyalty page, which the SDK fills in. */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly Placements $placements
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->placements->isOn(Placements::LOYALTY_PAGE)) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Rewards'));

        return $page;
    }
}
