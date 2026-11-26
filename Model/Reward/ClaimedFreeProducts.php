<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Reward;

use Loyaltylion\Core\Model\Api\Client;
use Loyaltylion\Core\Model\Config;
use Loyaltylion\Core\Model\Outbox\Outcome;
use Psr\Log\LoggerInterface;

/**
 * What LoyaltyLion says about a free product a shopper has claimed. This is
 * the only place that asks.
 *
 * A claim is one of two kinds. A seamless free product is held as a cart
 * session, known by its identifier. A free product code reward is a discount
 * code, which the store can add as a free line instead of asking the shopper
 * to paste it.
 */
class ClaimedFreeProducts
{
    // A shopper's request waits on this.
    private const TIMEOUT_SECONDS = 3;

    public const SESSION = 'session';
    public const CODE = 'code';

    private const PATHS = [
        self::SESSION => '/customers/%s/cart_sessions/%s',
        self::CODE => '/customers/%s/free_product_rewards/%s',
    ];

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
     *                    "reason" => string], or null when LoyaltyLion can't be reached. Only a failure
     *                    that another try could get past counts as unreachable: a caller keeps a reward
     *                    it could not check, and an answer such as a refused API key would then keep
     *                    every reward for as long as the store runs.
     */
    public function find($websiteId, $customerId, string $kind, string $id): ?array
    {
        if (!isset(self::PATHS[$kind])) {
            return ['outcome' => 'unavailable', 'reason' => 'not_found'];
        }

        if (!$customerId) {
            return ['outcome' => 'unavailable', 'reason' => 'signed_out'];
        }

        $connection = $this->config->forWebsite($websiteId);
        if (!$connection->sendsChanges()) {
            return ['outcome' => 'unavailable', 'reason' => 'not_connected'];
        }

        $response = $this->client->send(
            $connection,
            'GET',
            sprintf(self::PATHS[$kind], rawurlencode((string) $customerId), rawurlencode($id)),
            null,
            self::TIMEOUT_SECONDS
        );
        $answer = $response->json();

        if ($response->status === 200 && isset($answer['outcome'])) {
            return $answer;
        }

        $this->logger->error('[LoyaltyLion] Free product check failed - status: ' . $response->status);

        return Outcome::of($response->status) === Outcome::RETRY
            ? null
            : ['outcome' => 'unavailable', 'reason' => 'refused'];
    }
}
