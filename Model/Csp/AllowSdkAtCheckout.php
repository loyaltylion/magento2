<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Csp;

use Loyaltylion\Core\Model\Config;
use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\Policy\FetchPolicy;

/**
 * Lets LoyaltyLion's SDK load on pages with a strict Content Security Policy,
 * which Magento 2.4.7 and later apply to the checkout. Only while LoyaltyLion
 * is shown at checkout, the one thing that needs the SDK there.
 */
class AllowSdkAtCheckout implements PolicyCollectorInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly Placements $placements
    ) {
    }

    public function collect(array $defaultPolicies = []): array
    {
        try {
            if (!$this->placements->isOn(Placements::CHECKOUT_CART_REWARDS)) {
                return $defaultPolicies;
            }

            $connection = $this->config->forWebsite();
            $everything = ['script-src', 'connect-src', 'style-src', 'img-src', 'font-src'];
            $allowed = [
                [$connection->sdkHost, $everything],
                [$connection->apiHost, $everything],
                // The SDK's lazy script chunks, their CSS and its theme icons.
                [$connection->sdkStaticHost, ['script-src', 'style-src', 'img-src', 'font-src']],
                // The SDK's metrics.
                [$connection->platformHost, ['connect-src']],
            ];
            foreach ($allowed as [$host, $ids]) {
                foreach ($ids as $id) {
                    $defaultPolicies[] = new FetchPolicy($id, false, [$host]);
                }
            }
        } catch (\Throwable $e) {
            // A page must be served whatever happens here; without the policy the SDK is only refused.
            return $defaultPolicies;
        }

        return $defaultPolicies;
    }
}
