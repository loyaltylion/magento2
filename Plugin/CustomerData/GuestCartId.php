<?php

namespace Loyaltylion\Core\Plugin\CustomerData;

use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Psr\Log\LoggerInterface;

/**
 * Adds a guest's masked cart id to the `cart` section, so the LoyaltyLion SDK
 * can put a code on the guest's cart through `/V1/guest-carts/{id}/coupons`.
 * Luma reloads this section after every cart change, so the id is current on
 * every page, not only on the cart and checkout pages that carry
 * `checkoutConfig`.
 */
class GuestCartId
{
    private $checkoutSession;
    private $customerSession;
    private $quoteIdMaskFactory;
    private $quoteIdMaskResource;
    private $logger;

    public function __construct(
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteIdMaskResource $quoteIdMaskResource,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteIdMaskResource = $quoteIdMaskResource;
        $this->logger = $logger;
    }

    public function afterGetSectionData(Cart $subject, array $result)
    {
        // A signed-in shopper's cart is reached as `carts/mine` from their session.
        if ($this->customerSession->isLoggedIn()) {
            return $result;
        }

        $quoteId = (int) $this->checkoutSession->getQuoteId();
        if (!$quoteId || empty($result['summary_count'])) {
            return $result;
        }

        try {
            $mask = $this->quoteIdMaskFactory->create();
            $this->quoteIdMaskResource->load($mask, $quoteId, 'quote_id');
            if (!$mask->getMaskedId()) {
                // The same row Magento's checkout creates on its first visit.
                $mask->setQuoteId($quoteId);
                $this->quoteIdMaskResource->save($mask);
            }
            $result['loyaltylion_guest_cart_id'] = $mask->getMaskedId();
        } catch (\Exception $e) {
            $this->logger->error('[LoyaltyLion] Could not read the guest cart id: ' . $e->getMessage());
        }

        return $result;
    }
}
