<?php

declare(strict_types=1);

namespace Loyaltylion\Core\CustomerData;

use Loyaltylion\Core\Model\Shopper\Signer;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session;

/**
 * The signed-in shopper, for the storefront script to hand to LoyaltyLion's
 * SDK. It travels as private content, so pages stay cacheable.
 */
class Shopper implements SectionSourceInterface
{
    public function __construct(
        private readonly Session $session,
        private readonly Signer $signer
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
                $customer->getWebsiteId()
            ),
        ];
    }
}
