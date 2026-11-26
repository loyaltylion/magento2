<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Plugin;

use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Multishipping\Helper\Data;

/**
 * Keeps a cart that holds a reward out of the checkout with multiple
 * addresses. That checkout prices each address's items from the catalogue and
 * places its orders without the events a reward is checked and recorded on, so
 * the reward would be charged for and its claim left open.
 */
class NoMultishippingWithReward
{
    public function __construct(private readonly FreeProduct $freeProduct)
    {
    }

    public function afterIsMultishippingCheckoutAvailable(Data $subject, $result)
    {
        if (!$result) {
            return $result;
        }

        $quote = $subject->getQuote();

        return !($quote && $this->freeProduct->hasLines($quote));
    }
}
