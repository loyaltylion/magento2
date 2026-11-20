<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Controller\Cart;

use Magento\Framework\App\Action\HttpGetActionInterface;

class Index extends CartAction implements HttpGetActionInterface
{
    public function execute()
    {
        return $this->freeProduct->isOn() ? $this->cart() : $this->refuse(404, 'Not found');
    }
}
