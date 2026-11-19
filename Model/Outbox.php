<?php

namespace Loyaltylion\Core\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * Events waiting to be sent to LoyaltyLion, stored in `loyaltylion_outbox`.
 *
 * Observers add events here instead of calling our API inside the shopper's
 * request; OutboxSender sends them, retrying the ones that fail for reasons
 * that can pass (our service down, the program not launched yet).
 */
class Outbox
{
    const TABLE = "loyaltylion_outbox";

    const KIND_SIGNUP = "signup";
    const KIND_ORDER_CREATE = "order_create";
    const KIND_ORDER_UPDATE = "order_update";
    const KIND_CUSTOMER_EMAIL = "customer_email";
    const KIND_CUSTOMER_DELETE = "customer_delete";
    const KIND_NEWSLETTER_SIGNUP = "newsletter_signup";

    const STATUS_PENDING = "pending";
    const STATUS_SENT = "sent";
    const STATUS_FAILED = "failed";

    // An order's update waits a little so the create, which is added later in
    // the same request, is always in the table before cron looks at the order.
    const ORDER_UPDATE_DELAY_SECONDS = 30;
    const CLAIM_SECONDS = 120;

    private $_resource;
    private $_addedIds = [];

    public function __construct(ResourceConnection $resource)
    {
        $this->_resource = $resource;
    }

    /**
     * Adds an event and returns its id. An order update joins a pending update
     * for the same order instead, since its body is built from the order when
     * it is sent and so always carries the latest state.
     *
     * @param array|null $payload request body, or null to build it at send time
     */
    public function add($storeId, $kind, $subject, $payload = null, $delaySeconds = 0)
    {
        $connection = $this->_connection();
        $table = $this->_table();

        if ($kind === self::KIND_ORDER_UPDATE) {
            $existing = $connection->fetchOne(
                $connection
                    ->select()
                    ->from($table, ["id"])
                    ->where("subject = ?", $subject)
                    ->where("kind = ?", $kind)
                    ->where("status = ?", self::STATUS_PENDING)
                    ->order("id DESC")
                    ->limit(1)
            );
            if ($existing) {
                // A sender holding this row compares the version before it
                // marks the row sent, so a change made mid-send is sent again.
                $connection->update(
                    $table,
                    ["version" => new \Zend_Db_Expr("version + 1")],
                    ["id = ?" => $existing]
                );
                $this->_addedIds[] = (int) $existing;
                return (int) $existing;
            }
        }

        $now = time();
        $connection->insert($table, [
            "store_id" => (int) $storeId,
            "kind" => $kind,
            "subject" => $subject,
            "priority" => in_array($kind, [self::KIND_SIGNUP, self::KIND_ORDER_CREATE]) ? 0 : 1,
            "payload" => $payload === null ? null : json_encode($payload),
            "status" => self::STATUS_PENDING,
            "next_attempt_at" => $this->_datetime($now + $delaySeconds),
            "created_at" => $this->_datetime($now),
        ]);
        $id = (int) $connection->lastInsertId($table);
        $this->_addedIds[] = $id;
        return $id;
    }

    /**
     * Ids of the events added by this process, oldest first.
     */
    public function takeAddedIds()
    {
        $ids = array_values(array_unique($this->_addedIds));
        $this->_addedIds = [];
        return $ids;
    }

    /**
     * Pending events in the order they must be sent: order creates and
     * sign-ups before other events about the same order or customer, then
     * oldest first.
     *
     * @param int[]|null $ids only these events, whether or not they are due yet
     */
    public function findPending($limit, $ids = null)
    {
        $connection = $this->_connection();
        $select = $connection
            ->select()
            ->from($this->_table())
            ->where("status = ?", self::STATUS_PENDING)
            ->order(["priority ASC", "id ASC"])
            ->limit($limit);
        if ($ids === null) {
            $select->where("next_attempt_at <= ?", $this->_datetime(time()));
        } else {
            if (empty($ids)) {
                return [];
            }
            $select->where("id IN (?)", $ids);
        }
        return $connection->fetchAll($select);
    }

    /**
     * Whether an earlier pending event for the same order or customer must be
     * sent first. An order's update never goes before its create.
     */
    public function hasEarlierPending(array $row)
    {
        $connection = $this->_connection();
        $priority = (int) $row["priority"];
        $id = (int) $row["id"];
        return (bool) $connection->fetchOne(
            $connection
                ->select()
                ->from($this->_table(), ["id"])
                ->where("subject = ?", $row["subject"])
                ->where("status = ?", self::STATUS_PENDING)
                ->where("id <> ?", $id)
                ->where(
                    "priority < ? OR (priority = " . $priority . " AND id < " . $id . ")",
                    $priority
                )
                ->limit(1)
        );
    }

    /**
     * Takes the event for this process, so a request flushing its own events
     * and a cron run never send the same one twice. Returns false when someone
     * else holds it or it is no longer pending.
     */
    public function claim($id)
    {
        $now = time();
        return $this->_connection()->update(
            $this->_table(),
            ["claimed_until" => $this->_datetime($now + self::CLAIM_SECONDS)],
            [
                "id = ?" => (int) $id,
                "status = ?" => self::STATUS_PENDING,
                "claimed_until IS NULL OR claimed_until < ?" => $this->_datetime($now),
            ]
        ) === 1;
    }

    public function load($id)
    {
        $connection = $this->_connection();
        return $connection->fetchRow(
            $connection->select()->from($this->_table())->where("id = ?", (int) $id)
        ) ?: null;
    }

    /**
     * Marks the event sent, unless it changed while it was being sent; then it
     * stays pending and goes again straight away.
     */
    public function markSent(array $row, $status)
    {
        $connection = $this->_connection();
        $updated = $connection->update(
            $this->_table(),
            [
                "status" => self::STATUS_SENT,
                "sent_at" => $this->_datetime(time()),
                "attempts" => new \Zend_Db_Expr("attempts + 1"),
                "last_status" => $status,
                "last_error" => null,
                "claimed_until" => null,
            ],
            ["id = ?" => (int) $row["id"], "version = ?" => (int) $row["version"]]
        );
        if ($updated === 0) {
            $connection->update(
                $this->_table(),
                ["claimed_until" => null, "next_attempt_at" => $this->_datetime(time())],
                ["id = ?" => (int) $row["id"]]
            );
        }
    }

    public function markRetry(array $row, $status, $error, $nextAttemptAt)
    {
        $this->_connection()->update(
            $this->_table(),
            [
                "attempts" => new \Zend_Db_Expr("attempts + 1"),
                "last_status" => $status,
                "last_error" => $this->_truncate($error),
                "next_attempt_at" => $this->_datetime($nextAttemptAt),
                "claimed_until" => null,
            ],
            ["id = ?" => (int) $row["id"]]
        );
    }

    public function markFailed(array $row, $status, $error)
    {
        $this->_connection()->update(
            $this->_table(),
            [
                "status" => self::STATUS_FAILED,
                "attempts" => new \Zend_Db_Expr("attempts + 1"),
                "last_status" => $status,
                "last_error" => $this->_truncate($error),
                "claimed_until" => null,
            ],
            ["id = ?" => (int) $row["id"]]
        );
    }

    public function release(array $row)
    {
        $this->_connection()->update(
            $this->_table(),
            ["claimed_until" => null],
            ["id = ?" => (int) $row["id"]]
        );
    }

    /**
     * Holds every pending event of a store until the given time, after a
     * failure that would fail them all the same way.
     */
    public function holdStore($storeId, $until)
    {
        $this->_connection()->update(
            $this->_table(),
            ["next_attempt_at" => $this->_datetime($until)],
            [
                "store_id = ?" => (int) $storeId,
                "status = ?" => self::STATUS_PENDING,
                "next_attempt_at < ?" => $this->_datetime($until),
            ]
        );
    }

    public function listByStatus($status, $limit)
    {
        $connection = $this->_connection();
        $select = $connection
            ->select()
            ->from($this->_table(), [
                "id", "store_id", "kind", "subject", "status", "attempts",
                "next_attempt_at", "last_status", "last_error", "created_at", "sent_at",
            ])
            ->order("id DESC")
            ->limit($limit);
        if ($status) {
            $select->where("status = ?", $status);
        }
        return $connection->fetchAll($select);
    }

    /**
     * Puts failed events back in the queue to be sent on the next run.
     *
     * @param int[]|null $ids the events, or null for every failed one
     */
    public function retryFailed($ids = null)
    {
        $where = ["status = ?" => self::STATUS_FAILED];
        if ($ids !== null) {
            $where["id IN (?)"] = $ids;
        }
        return $this->_connection()->update(
            $this->_table(),
            [
                "status" => self::STATUS_PENDING,
                "attempts" => 0,
                "next_attempt_at" => $this->_datetime(time()),
                "claimed_until" => null,
            ],
            $where
        );
    }

    public function deleteSentBefore($timestamp)
    {
        return $this->_connection()->delete($this->_table(), [
            "status = ?" => self::STATUS_SENT,
            "sent_at < ?" => $this->_datetime($timestamp),
        ]);
    }

    private function _connection()
    {
        return $this->_resource->getConnection();
    }

    private function _table()
    {
        return $this->_resource->getTableName(self::TABLE);
    }

    private function _datetime($timestamp)
    {
        return gmdate("Y-m-d H:i:s", $timestamp);
    }

    private function _truncate($text)
    {
        return $text === null ? null : mb_substr((string) $text, 0, 2000);
    }
}
