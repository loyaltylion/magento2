<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Outbox;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * The orders and customers whose current state LoyaltyLion has not been sent.
 * A row names the entity, not what changed: the sender reads it again when it
 * sends, so a row is enough however many times the entity changed meanwhile.
 */
class Outbox
{
    public const ORDER = 'order';
    public const CUSTOMER = 'customer';
    public const CUSTOMER_REGISTERED = 'customer_registered';
    public const CUSTOMER_DELETED = 'customer_deleted';
    public const CART_SESSION_RELEASED = 'cart_session_released';
    public const NEWSLETTER_SIGNUP = 'newsletter_signup';

    private const TABLE = 'loyaltylion_outbox';
    private const WAITING = 'waiting';
    private const HELD = 'held';
    private const PARKED = 'parked';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param array|null $payload only for a row whose entity can't be read again when it is sent
     */
    public function add(string $kind, int $entityId, int $websiteId, ?array $payload = null): void
    {
        $this->db()->insertOnDuplicate(
            $this->table(),
            [
                'kind' => $kind,
                'entity_id' => $entityId,
                'website_id' => $websiteId,
                'payload' => $payload === null ? null : json_encode($payload),
            ],
            [
                'payload',
                'state' => new \Zend_Db_Expr($this->db()->quote(self::WAITING)),
                'attempts' => new \Zend_Db_Expr('0'),
                'version' => new \Zend_Db_Expr('version + 1'),
                'available_at' => new \Zend_Db_Expr('CURRENT_TIMESTAMP'),
                'last_error' => new \Zend_Db_Expr('NULL'),
            ]
        );
    }

    /**
     * @return int[] the websites with rows ready to send
     */
    public function dueWebsites(): array
    {
        $select = $this->db()->select()
            ->distinct()
            ->from($this->table(), ['website_id'])
            ->where('state IN (?)', [self::WAITING, self::HELD])
            ->where('available_at <= CURRENT_TIMESTAMP')
            ->order('website_id ASC');

        return array_map('intval', $this->db()->fetchCol($select));
    }

    /**
     * @return array[] one website's rows ready to send, oldest first
     */
    public function due(int $websiteId, int $limit): array
    {
        $select = $this->db()->select()
            ->from($this->table())
            ->where('website_id = ?', $websiteId)
            ->where('state IN (?)', [self::WAITING, self::HELD])
            ->where('available_at <= CURRENT_TIMESTAMP')
            ->order('available_at ASC')
            ->limit($limit);

        return $this->db()->fetchAll($select);
    }

    /**
     * @return array|null the order's row if it is ready to send
     */
    public function dueOrder(int $orderId): ?array
    {
        $select = $this->db()->select()
            ->from($this->table())
            ->where('kind = ?', self::ORDER)
            ->where('entity_id = ?', $orderId)
            ->where('state IN (?)', [self::WAITING, self::HELD])
            ->where('available_at <= CURRENT_TIMESTAMP');

        return $this->db()->fetchRow($select) ?: null;
    }

    /**
     * Forgets a row once its state is with LoyaltyLion, unless the entity
     * changed again while it was being sent.
     */
    public function sent(array $row): void
    {
        $this->db()->delete(
            $this->table(),
            ['id = ?' => $row['id'], 'version = ?' => $row['version']]
        );
    }

    public function retryLater(array $row, int $seconds, string $error): void
    {
        $this->move($row, self::WAITING, $seconds, $error, true);
    }

    /**
     * Keeps a row for a program that has not launched, or an API key that was
     * refused: neither is the entity's fault, so it does not count as a try.
     */
    public function hold(array $row, int $seconds, string $reason): void
    {
        $this->move($row, self::HELD, $seconds, $reason, false);
    }

    /**
     * Holds every row a website has ready, for a reason that applies to all of
     * them, so the next runs don't ask LoyaltyLion the same thing row by row.
     */
    public function holdWebsite(int $websiteId, int $seconds, string $reason): void
    {
        $this->db()->update(
            $this->table(),
            [
                'state' => self::HELD,
                'available_at' => new \Zend_Db_Expr(sprintf('DATE_ADD(CURRENT_TIMESTAMP, INTERVAL %d SECOND)', $seconds)),
                'last_error' => mb_substr($reason, 0, 255),
            ],
            [
                'website_id = ?' => $websiteId,
                'state IN (?)' => [self::WAITING, self::HELD],
                'available_at <= CURRENT_TIMESTAMP',
            ]
        );
    }

    /**
     * Sets aside a row LoyaltyLion will never accept as it is. It is sent
     * again only if the entity changes.
     */
    public function park(array $row, string $error): void
    {
        $this->move($row, self::PARKED, 0, $error, true);
    }

    /**
     * Makes the parked rows ready to send again, for when what LoyaltyLion
     * refused them for has been put right.
     *
     * @param int|null $websiteId null takes every website's rows
     * @return int how many rows
     */
    public function retryParked(?int $websiteId = null): int
    {
        $where = ['state = ?' => self::PARKED];
        if ($websiteId !== null) {
            $where['website_id = ?'] = $websiteId;
        }

        return (int) $this->db()->update(
            $this->table(),
            [
                'state' => self::WAITING,
                'attempts' => 0,
                'available_at' => new \Zend_Db_Expr('CURRENT_TIMESTAMP'),
            ],
            $where
        );
    }

    /**
     * @param int|null $websiteId null counts every website's rows
     * @return array{waiting: int, parked: int, oldest_waiting_at: ?string}
     */
    public function summary(?int $websiteId = null): array
    {
        $db = $this->db();
        $select = $db->select();
        if ($websiteId !== null) {
            $select->where('website_id = ?', $websiteId);
        }
        $row = $db->fetchRow(
            $select->from($this->table(), [
                'waiting' => new \Zend_Db_Expr($db->quoteInto('COALESCE(SUM(state <> ?), 0)', self::PARKED)),
                'parked' => new \Zend_Db_Expr($db->quoteInto('COALESCE(SUM(state = ?), 0)', self::PARKED)),
                'oldest_waiting_at' => new \Zend_Db_Expr(
                    $db->quoteInto('MIN(CASE WHEN state <> ? THEN created_at END)', self::PARKED)
                ),
            ])
        );

        return [
            'waiting' => (int) $row['waiting'],
            'parked' => (int) $row['parked'],
            'oldest_waiting_at' => $row['oldest_waiting_at'],
        ];
    }

    /**
     * Leaves the row as it is if the entity changed while it was being sent:
     * `add` has already made it ready again, and what LoyaltyLion said was
     * about the older state.
     */
    private function move(array $row, string $state, int $seconds, ?string $error, bool $countsAsTry): void
    {
        $this->db()->update(
            $this->table(),
            [
                'state' => $state,
                'available_at' => new \Zend_Db_Expr(sprintf('DATE_ADD(CURRENT_TIMESTAMP, INTERVAL %d SECOND)', $seconds)),
                'attempts' => new \Zend_Db_Expr($countsAsTry ? 'attempts + 1' : 'attempts'),
                'last_error' => $error === null ? null : mb_substr($error, 0, 255),
            ],
            ['id = ?' => $row['id'], 'version = ?' => $row['version']]
        );
    }

    private function db(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function table(): string
    {
        return $this->resource->getTableName(self::TABLE);
    }
}
