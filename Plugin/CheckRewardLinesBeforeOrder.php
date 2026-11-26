<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Plugin;

use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;

/**
 * Stops an order whose cart holds a reward LoyaltyLion no longer allows, before
 * Magento starts placing it. Every way of placing an order reaches `submit`,
 * and nothing has been reserved or deducted for the order at that point, which
 * is not true of the events dispatched inside it.
 */
class CheckRewardLinesBeforeOrder
{
    public function __construct(private readonly FreeProduct $freeProduct)
    {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws LocalizedException
     */
    public function beforeSubmit(QuoteManagement $subject, Quote $quote, $orderData = []): void
    {
        if ($this->freeProduct->removeRefusedLines($quote)) {
            throw new LocalizedException(
                __('A reward in your cart is no longer available, so we removed it. Check your order and place it again.')
            );
        }
    }
}
