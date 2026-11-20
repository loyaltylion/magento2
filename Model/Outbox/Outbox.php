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

    private const TABLE = 'loyaltylion_outbox';
    private const WAITING = 'waiting';
    private const HELD = 'held';
    private const PARKED = 'parked';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function add(string $kind, int $entityId, int $websiteId): void
    {
        $this->db()->insertOnDuplicate(
            $this->table(),
            [
                'kind' => $kind,
                'entity_id' => $entityId,
                'website_id' => $websiteId,
            ],
            [
                'state' => new \Zend_Db_Expr($this->db()->quote(self::WAITING)),
                'attempts' => new \Zend_Db_Expr('0'),
                'version' => new \Zend_Db_Expr('version + 1'),
                'available_at' => new \Zend_Db_Expr('CURRENT_TIMESTAMP'),
                'last_error' => new \Zend_Db_Expr('NULL'),
            ]
        );
    }

    /**
     * @return array[] rows ready to send, oldest first
     */
    public function due(int $limit): array
    {
        $select = $this->db()->select()
            ->from($this->table())
            ->where('state IN (?)', [self::WAITING, self::HELD])
            ->where('available_at <= CURRENT_TIMESTAMP')
            ->order('available_at ASC')
            ->limit($limit);

        return $this->db()->fetchAll($select);
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
     * Sets aside a row LoyaltyLion will never accept as it is. It is sent
     * again only if the entity changes.
     */
    public function park(array $row, string $error): void
    {
        $this->move($row, self::PARKED, 0, $error, true);
    }

    /**
     * @return array{waiting: int, parked: int, oldest_waiting_at: ?string}
     */
    public function summary(): array
    {
        $db = $this->db();
        $row = $db->fetchRow(
            $db->select()->from($this->table(), [
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
            ['id = ?' => $row['id']]
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
