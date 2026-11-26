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
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends what is waiting in the outbox to LoyaltyLion.
 */
class Sender
{
    private const LOCK = 'loyaltylion_outbox_send';
    private const HOLD_SECONDS = 900;
    private const RUN_SECONDS = 40;
    private const SCHEDULED_TIMEOUT_SECONDS = 5;
    // The order's confirmation page is waiting, and so is the shopper's session.
    private const WAITED_ON_TIMEOUT_SECONDS = 3;
    private const NO_SETTINGS = 'Waiting for this website\'s Site ID and API key';
    private const NOT_LAUNCHED = 'The loyalty program has not launched';

    public function __construct(
        private readonly Outbox $outbox,
        private readonly Config $config,
        private readonly Client $client,
        private readonly OrderRepositoryInterface $orders,
        private readonly CustomerRepositoryInterface $customers,
        private readonly OrderState $orderState,
        private readonly CustomerState $customerState,
        private readonly LockManagerInterface $lock,
        private readonly LoggerInterface $logger,
        private readonly DateTime $clock
    ) {
    }

    /**
     * Sends what is ready, website by website, so one website's backlog or
     * refused key can't keep another's rows waiting.
     *
     * @param int $limit the most rows to send for one website
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
            $stopAt = $this->clock->gmtTimestamp() + self::RUN_SECONDS;
            $unreachable = 0;

            foreach ($this->outbox->dueWebsites() as $websiteId) {
                $connection = $this->config->forWebsite($websiteId);
                if (!$connection->sendsChanges()) {
                    $this->outbox->holdWebsite($websiteId, self::HOLD_SECONDS, self::NO_SETTINGS);
                    $counts[Outcome::HELD] = ($counts[Outcome::HELD] ?? 0) + 1;
                    continue;
                }

                foreach ($this->outbox->due($websiteId, $limit) as $row) {
                    // The job shares the store's cron with everything else
                    // scheduled, so a run ends on time and the next continues.
                    if ($this->clock->gmtTimestamp() >= $stopAt) {
                        break 2;
                    }

                    $status = null;
                    try {
                        $outcome = $this->sendRow($row, $connection, self::SCHEDULED_TIMEOUT_SECONDS, $status);
                    } catch (\Throwable $e) {
                        // One order or customer the store can't read must not hold
                        // up the rows behind it.
                        $outcome = Outcome::RETRY;
                        $this->outbox->retryLater(
                            $row,
                            Outcome::waitBeforeRetry((int) $row['attempts']),
                            'Could not be read: ' . $e->getMessage()
                        );
                        $this->logger->error(
                            sprintf('[LoyaltyLion] %s %d could not be sent: %s', $row['kind'], $row['entity_id'], $e->getMessage())
                        );
                    }
                    $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;

                    // Every other row of the website would get the same answer.
                    if ($outcome === Outcome::HELD || $outcome === Outcome::REFUSED_KEY) {
                        $this->outbox->holdWebsite(
                            $websiteId,
                            self::HOLD_SECONDS,
                            $outcome === Outcome::HELD ? self::NOT_LAUNCHED : 'The API key was refused'
                        );
                        continue 2;
                    }

                    // LoyaltyLion is the same host for every website, so when it
                    // can't be reached twice running the rest wait for the next run.
                    $unreachable = $status === 0 ? $unreachable + 1 : 0;
                    if ($unreachable >= 2) {
                        break 2;
                    }
                }
            }
        } finally {
            $this->lock->unlock(self::LOCK);
        }

        return $counts;
    }

    /**
     * Sends one order now if it is waiting, so the page that confirms it can
     * say what it earned without waiting for the next scheduled run.
     *
     * @return string|null the Outcome, or null when the order isn't waiting or a run is already sending
     */
    public function sendOrder(int $orderId): ?string
    {
        if (!$this->lock->lock(self::LOCK, 0)) {
            return null;
        }

        try {
            $row = $this->outbox->dueOrder($orderId);
            if ($row === null) {
                return null;
            }

            try {
                return $this->sendRow(
                    $row,
                    $this->config->forWebsite((int) $row['website_id']),
                    self::WAITED_ON_TIMEOUT_SECONDS
                );
            } catch (\Throwable $e) {
                // The scheduled run meets the same failure and records it on the row.
                $this->logger->warning(
                    sprintf('[LoyaltyLion] order %d could not be sent from its confirmation page: %s', $orderId, $e->getMessage())
                );

                return Outcome::RETRY;
            }
        } finally {
            $this->lock->unlock(self::LOCK);
        }
    }

    /**
     * @param int|null $status set to LoyaltyLion's HTTP status, or 0 when it could not be reached
     */
    private function sendRow(array $row, Connection $connection, int $timeout, ?int &$status = null): string
    {
        if (!$connection->sendsChanges()) {
            $this->outbox->hold($row, self::HOLD_SECONDS, self::NO_SETTINGS);

            return Outcome::HELD;
        }

        $response = $this->request($row, $connection, $timeout);
        if ($response === null) {
            $this->outbox->sent($row);

            return Outcome::SENT;
        }

        $status = $response->status;
        $outcome = Outcome::of($response->status);
        $error = sprintf('%d %s', $response->status, $response->body);

        switch ($outcome) {
            case Outcome::SENT:
                $this->outbox->sent($row);
                break;
            case Outcome::HELD:
                $this->outbox->hold($row, self::HOLD_SECONDS, self::NOT_LAUNCHED);
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
    private function request(array $row, Connection $connection, int $timeout): ?Response
    {
        $id = (int) $row['entity_id'];

        try {
            switch ($row['kind']) {
                case Outbox::ORDER:
                    return $this->client->send(
                        $connection,
                        'PUT',
                        '/orders/' . $id,
                        $this->orderState->of($this->orders->get($id)),
                        $timeout
                    );
                case Outbox::CUSTOMER:
                    return $this->client->send(
                        $connection,
                        'PUT',
                        '/customers/' . $id,
                        $this->customerState->of($this->customers->getById($id)),
                        $timeout
                    );
                case Outbox::CUSTOMER_REGISTERED:
                    return $this->client->send(
                        $connection,
                        'POST',
                        '/customers/' . $id . '/registration',
                        $this->customerState->of($this->customers->getById($id)),
                        $timeout
                    );
                case Outbox::NEWSLETTER_SIGNUP:
                    return $this->client->send(
                        $connection,
                        'POST',
                        '/customers/' . $id . '/newsletter_signup',
                        null,
                        $timeout
                    );
                case Outbox::CUSTOMER_DELETED:
                    return $this->client->send($connection, 'DELETE', '/customers/' . $id, null, $timeout);
                case Outbox::CART_SESSION_RELEASED:
                    $released = json_decode((string) $row['payload'], true) ?: [];

                    return $this->client->send($connection, 'DELETE', sprintf(
                        '/customers/%s/cart_sessions/%s',
                        rawurlencode((string) ($released['customer_id'] ?? '')),
                        rawurlencode((string) ($released['session'] ?? ''))
                    ), null, $timeout);
                default:
                    return null;
            }
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
