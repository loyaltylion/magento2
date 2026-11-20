<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model;

use Loyaltylion\Core\Model\Api\Client;
use Loyaltylion\Core\Model\Outbox\Outbox;
use Loyaltylion\Core\Model\Outbox\Timestamp;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\PackageInfo;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Tells LoyaltyLion that each connected website's module is running, which
 * versions it is on and how much it still has to send. It goes out from cron,
 * so a store whose cron has stopped goes quiet, which is the thing to notice.
 */
class Heartbeat
{
    public function __construct(
        private readonly Config $config,
        private readonly Client $client,
        private readonly Outbox $outbox,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductMetadataInterface $magento,
        private readonly PackageInfo $packageInfo,
        private readonly Capabilities $capabilities
    ) {
    }

    /**
     * @return int[] the HTTP status LoyaltyLion answered for each website id, 0 when unreachable
     */
    public function send(): array
    {
        $answers = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $connection = $this->config->forWebsite($website->getId());
            if (!$connection->sendsChanges()) {
                continue;
            }

            $outbox = $this->outbox->summary((int) $website->getId());
            $answers[(int) $website->getId()] = $this->client->send($connection, 'POST', '/heartbeat', [
                'module_version' => (string) $this->packageInfo->getVersion('Loyaltylion_Core'),
                'magento_version' => (string) $this->magento->getVersion(),
                'magento_edition' => (string) $this->magento->getEdition(),
                'capabilities' => $this->capabilities->all(),
                'outbox' => array_filter([
                    'waiting' => $outbox['waiting'],
                    'parked' => $outbox['parked'],
                    'oldest_waiting_at' => $outbox['oldest_waiting_at'] === null
                        ? null
                        : Timestamp::utc($outbox['oldest_waiting_at']),
                ], static fn ($value) => $value !== null),
            ])->status;
        }

        return $answers;
    }
}
