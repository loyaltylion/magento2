<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Controller\Cart;

use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * The cart as the SDK reads it, for everything the SDK shows from the cart:
 * the Cart rewards block and points estimates as well as free products. It
 * answers wherever the website shows the SDK. Adding and removing lines is
 * only for free products, and stays behind their setting.
 */
class Index extends CartAction implements HttpGetActionInterface
{
    public function execute()
    {
        return $this->sdkCart->isServed() ? $this->cart() : $this->refuse(404, 'Not found');
    }
}
