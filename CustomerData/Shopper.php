<?php

declare(strict_types=1);

namespace Loyaltylion\Core\CustomerData;

use Loyaltylion\Core\Model\Shopper\Signer;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The signed-in shopper, for the storefront script to hand to LoyaltyLion's
 * SDK. It travels as private content, so pages stay cacheable.
 */
class Shopper implements SectionSourceInterface
{
    public function __construct(
        private readonly Session $session,
        private readonly Signer $signer,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getSectionData(): array
    {
        if (!$this->session->isLoggedIn()) {
            return ['packet' => null];
        }

        $customer = $this->session->getCustomer();

        return [
            'packet' => $this->signer->packet(
                (string) $customer->getId(),
                (string) $customer->getEmail(),
                // The website being browsed, whose site the page's SDK was
                // started for. Where accounts are shared between websites it
                // is not always the one the customer registered on.
                $this->storeManager->getStore()->getWebsiteId()
            ),
        ];
    }
}
