<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Reward;

use Loyaltylion\Core\Model\Api\Client;
use Loyaltylion\Core\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * What LoyaltyLion says about a free product a shopper has claimed. This is
 * the only place that asks, so the rest of the module does not depend on how
 * a claim is identified or which of LoyaltyLion's APIs answers.
 */
class ClaimedFreeProducts
{
    // A shopper's request waits on this.
    private const TIMEOUT_SECONDS = 3;

    public function __construct(
        private readonly Config $config,
        private readonly Client $client,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int|string|null $websiteId
     * @param int|string|null $customerId
     * @return array|null ["outcome" => "available", "sku" => string], or ["outcome" => "unavailable",
     *                    "reason" => string], or null when LoyaltyLion can't be reached
     */
    public function find($websiteId, $customerId, string $code): ?array
    {
        if (!$customerId) {
            return ['outcome' => 'unavailable', 'reason' => 'signed_out'];
        }

        $connection = $this->config->forWebsite($websiteId);
        if (!$connection->sendsChanges()) {
            return null;
        }

        $response = $this->client->send(
            $connection,
            'GET',
            sprintf('/customers/%s/free_product_rewards/%s', rawurlencode((string) $customerId), rawurlencode($code)),
            null,
            self::TIMEOUT_SECONDS
        );
        $answer = $response->json();

        if ($response->status !== 200 || !isset($answer['outcome'])) {
            $this->logger->error('[LoyaltyLion] Free product check failed - status: ' . $response->status);

            return null;
        }

        return $answer;
    }
}
