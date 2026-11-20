<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Outbox;

use Loyaltylion\Core\Model\Api\Client;
use Loyaltylion\Core\Model\Api\Response;
use Loyaltylion\Core\Model\Config;
use Loyaltylion\Core\Model\Connection;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends what is waiting in the outbox to LoyaltyLion.
 */
class Sender
{
    private const LOCK = 'loyaltylion_outbox_send';
    private const HOLD_SECONDS = 900;

    public function __construct(
        private readonly Outbox $outbox,
        private readonly Config $config,
        private readonly Client $client,
        private readonly OrderRepositoryInterface $orders,
        private readonly CustomerRepositoryInterface $customers,
        private readonly OrderState $orderState,
        private readonly CustomerState $customerState,
        private readonly LockManagerInterface $lock,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return int[] how many rows met each outcome, keyed by Outcome constant
     */
    public function send(int $limit = 200): array
    {
        $counts = [];

        // Two runs at once would send the same rows twice.
        if (!$this->lock->lock(self::LOCK, 0)) {
            return $counts;
        }

        try {
            $refusedWebsites = [];

            foreach ($this->outbox->due($limit) as $row) {
                $websiteId = (int) $row['website_id'];
                if (isset($refusedWebsites[$websiteId])) {
                    continue;
                }

                $outcome = $this->sendRow($row, $this->config->forWebsite($websiteId));
                $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;

                // Every other row of the website would be refused the same way.
                if ($outcome === Outcome::REFUSED_KEY) {
                    $refusedWebsites[$websiteId] = true;
                }
            }
        } finally {
            $this->lock->unlock(self::LOCK);
        }

        return $counts;
    }

    private function sendRow(array $row, Connection $connection): string
    {
        if (!$connection->sendsChanges()) {
            $this->outbox->hold($row, self::HOLD_SECONDS, 'This website is not connected to LoyaltyLion');

            return Outcome::HELD;
        }

        $response = $this->request($row, $connection);
        if ($response === null) {
            $this->outbox->sent($row);

            return Outcome::SENT;
        }

        $outcome = Outcome::of($response->status);
        $error = sprintf('%d %s', $response->status, $response->body);

        switch ($outcome) {
            case Outcome::SENT:
                $this->outbox->sent($row);
                break;
            case Outcome::HELD:
                $this->outbox->hold($row, self::HOLD_SECONDS, 'The loyalty program has not launched');
                break;
            case Outcome::REFUSED_KEY:
                $this->outbox->hold($row, self::HOLD_SECONDS, $error);
                $this->logger->error('[LoyaltyLion] The API key was refused: ' . $error);
                break;
            case Outcome::RETRY:
                $this->outbox->retryLater($row, Outcome::waitBeforeRetry((int) $row['attempts']), $error);
                break;
            default:
                $this->outbox->park($row, $error);
                $this->logger->error(
                    sprintf('[LoyaltyLion] %s %d was refused: %s', $row['kind'], $row['entity_id'], $error)
                );
        }

        return $outcome;
    }

    /**
     * @return Response|null null when the entity no longer exists, so there is nothing to send
     */
    private function request(array $row, Connection $connection): ?Response
    {
        $id = (int) $row['entity_id'];

        try {
            switch ($row['kind']) {
                case Outbox::ORDER:
                    return $this->client->send(
                        $connection,
                        'PUT',
                        '/orders/' . $id,
                        $this->orderState->of($this->orders->get($id))
                    );
                case Outbox::CUSTOMER:
                    return $this->client->send(
                        $connection,
                        'PUT',
                        '/customers/' . $id,
                        $this->customerState->of($this->customers->getById($id))
                    );
                case Outbox::CUSTOMER_REGISTERED:
                    return $this->client->send(
                        $connection,
                        'POST',
                        '/customers/' . $id . '/registration',
                        $this->customerState->of($this->customers->getById($id))
                    );
                case Outbox::CUSTOMER_DELETED:
                    return $this->client->send($connection, 'DELETE', '/customers/' . $id);
                default:
                    return null;
            }
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
