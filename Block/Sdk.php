<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Block;

use Loyaltylion\Core\Model\Config;
use Loyaltylion\Core\Model\Connection;
use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Sdk extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly Placements $placements,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Nothing on a page that only wants the SDK for a placement the merchant
     * has off, as the checkout does.
     */
    protected function _toHtml()
    {
        $placement = (string) $this->getData('needs_placement');

        return $placement === '' || $this->placements->isOn($placement) ? parent::_toHtml() : '';
    }

    public function getConnection(): Connection
    {
        return $this->config->forWebsite();
    }
}
