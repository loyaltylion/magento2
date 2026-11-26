<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Plugin\CustomerData;

use Loyaltylion\Core\Model\Config;
use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;

/**
 * Adds a guest's masked cart id to the `cart` section, as
 * `loyaltylion_guest_cart_id`, so the SDK can name the cart to Magento's
 * guest cart API on any page. It travels as private content, to its own
 * shopper only, and Magento reloads the section whenever the cart changes.
 * A signed-in shopper's cart is `carts/mine`, which needs no id.
 */
class GuestCartId
{
    public const KEY = 'loyaltylion_guest_cart_id';

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly QuoteIdToMaskedQuoteIdInterface $maskedIds,
        private readonly Config $config
    ) {
    }

    /**
     * @param Cart $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetSectionData(Cart $subject, array $result): array
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->getId() || $quote->getCustomerId() || !$this->config->forWebsite()->showsSdk()) {
            return $result;
        }

        try {
            $maskedId = $this->maskedIds->execute((int) $quote->getId());
        } catch (NoSuchEntityException $e) {
            return $result;
        }

        if ($maskedId !== '') {
            $result[self::KEY] = $maskedId;
        }

        return $result;
    }
}
