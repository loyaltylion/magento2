<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Block;

use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Customer\Block\Account\SortLink;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Template\Context;

/** The Rewards link in the customer's account menu, shown only where the page it leads to is. */
class AccountLink extends SortLink
{
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        private readonly Placements $placements,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    protected function _toHtml()
    {
        return $this->placements->isOn(Placements::ACCOUNT_PAGE) ? parent::_toHtml() : '';
    }
}
