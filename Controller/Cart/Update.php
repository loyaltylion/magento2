<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Controller\Cart;

use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Takes lines out of the cart the way the SDK asks Shopify to: `updates` maps
 * a line's key to a quantity of zero. Only reward lines are removed, which
 * are the only lines the SDK removes.
 */
class Update extends CartAction implements HttpPostActionInterface
{
    public function execute()
    {
        if (!$this->freeProduct->isOn()) {
            return $this->refuse(404, 'Not found');
        }

        $keys = [];
        foreach ((array) ($this->body()['updates'] ?? []) as $key => $quantity) {
            if ((int) $quantity === 0) {
                $keys[] = (string) $key;
            }
        }
        $this->freeProduct->removeLines($this->checkoutSession->getQuote(), $keys);

        return $this->cart();
    }
}
