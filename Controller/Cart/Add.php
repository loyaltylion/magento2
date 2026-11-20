<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Controller\Cart;

use Loyaltylion\Core\Model\Cart\SdkCart;
use Loyaltylion\Core\Model\Reward\ClaimedFreeProducts;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Adds a line the way the SDK asks Shopify to. The only line it adds here is
 * a seamless free product, named by the cart session in the line's properties.
 */
class Add extends CartAction implements HttpPostActionInterface
{
    public function execute()
    {
        if (!$this->freeProduct->isOn()) {
            return $this->refuse(404, 'Not found');
        }

        $session = (string) ($this->body()['properties'][SdkCart::SESSION_PROPERTY] ?? '');
        $quote = $this->checkoutSession->getQuote();
        $answer = $this->freeProduct->addToCart(
            $quote,
            $quote->getCustomerId(),
            ClaimedFreeProducts::SESSION,
            $session
        );

        // The SDK reads any 422 as "out of stock" unless it says the variant
        // can't be found.
        return $answer['added']
            ? $this->cart()
            : $this->refuse(422, $answer['reason'] === 'out_of_stock' ? 'Sold out' : 'Cannot find variant');
    }
}
