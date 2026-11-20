<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Outbox;

use Loyaltylion\Core\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Where the store's observers note that an order or customer changed.
 */
class Queue
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int|string|null $entityId
     * @param int|string|null $websiteId
     */
    public function add(string $kind, $entityId, $websiteId, ?array $payload = null): void
    {
        // This runs inside the shopper's or the admin's own save, which must
        // go through whatever happens here.
        try {
            if ($entityId && $this->config->forWebsite($websiteId)->queuesChanges()) {
                $this->outbox->add($kind, (int) $entityId, (int) $websiteId, $payload);
            }
        } catch (\Throwable $e) {
            $this->logger->error('[LoyaltyLion] Could not queue a change: ' . $e->getMessage());
        }
    }
}
