<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Csp;

use Loyaltylion\Core\Model\Config;
use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\Policy\FetchPolicy;
use Psr\Log\LoggerInterface;

/**
 * Lets LoyaltyLion's SDK load on pages with a strict Content Security Policy.
 * Magento 2.4.7 and later apply one to the checkout, and a store can apply one
 * to every page, so the allowance follows the SDK: wherever the website shows
 * it, whichever placements are on.
 */
class AllowSdkAtCheckout implements PolicyCollectorInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function collect(array $defaultPolicies = []): array
    {
        try {
            $connection = $this->config->forWebsite();
            if (!$connection->showsSdk()) {
                return $defaultPolicies;
            }

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
            $this->logger->warning('[LoyaltyLion] The SDK was not allowed by the page\'s policy: ' . $e->getMessage());

            return $defaultPolicies;
        }

        return $defaultPolicies;
    }
}
