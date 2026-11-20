<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Block;

use Loyaltylion\Core\Model\Config;
use Loyaltylion\Core\Model\Connection;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Sdk extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getConnection(): Connection
    {
        return $this->config->forWebsite();
    }
}
