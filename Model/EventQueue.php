<?php

namespace Loyaltylion\Core\Model;

use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Queues events for LoyaltyLion in the outbox and sends them.
 *
 * Events a request adds are sent once the request has finished (after the
 * response has gone to the shopper where PHP-FPM or LiteSpeed allows it), and
 * the cron job sends whatever is still pending: events that failed, and events
 * from a request that ended before it could send them. So an event is lost
 * neither when our service fails nor when the store's cron is not running.
 */
class EventQueue
{
    const BATCH_SIZE = 500;
    const TIMEOUT_SECONDS = 10;
    // While the shopper still waits for the response, as 1.4.0's calls did.
    const IN_REQUEST_TIMEOUT_SECONDS = 5;
    const KEEP_SENT_SECONDS = 7 * 86400;

    private $_outbox;
    private $_config;
    private $_orderRepository;
    private $_orderTools;
    private $_logger;
    private $_flushScheduled = false;
    private $_timeout = self::TIMEOUT_SECONDS;

    public function __construct(
        Outbox $outbox,
        \Loyaltylion\Core\Helper\Config $config,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Loyaltylion\Core\Helper\OrderTools $orderTools,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_outbox = $outbox;
        $this->_config = $config;
        $this->_orderRepository = $orderRepository;
        $this->_orderTools = $orderTools;
        $this->_logger = $logger;
    }

    /**
     * @param array|null $payload request body, or null when it is built at send time
     */
    public function add($storeId, $kind, $subject, $payload = null, $delaySeconds = 0)
    {
        $id = $this->_outbox->add($storeId, $kind, $subject, $payload, $delaySeconds);
        if (!$this->_flushScheduled) {
            $this->_flushScheduled = true;
            register_shutdown_function([$this, "flushAtShutdown"]);
        }
        return $id;
    }

    public function flushAtShutdown()
    {
        try {
            if (function_exists("fastcgi_finish_request")) {
                fastcgi_finish_request();
            } elseif (function_exists("litespeed_finish_request")) {
                litespeed_finish_request();
            } else {
                $this->_timeout = self::IN_REQUEST_TIMEOUT_SECONDS;
            }
            $this->sendNow($this->_outbox->takeAddedIds());
        } catch (\Throwable $e) {
            $this->_logger->error(
                "[LoyaltyLion] Could not send queued events: " . $e->getMessage()
            );
        }
    }

    /**
     * Sends every event that is due. Returns counts by outcome.
     */
    public function sendDue()
    {
        $counts = $this->_send($this->_outbox->findPending(self::BATCH_SIZE));
        $this->_outbox->deleteSentBefore(time() - self::KEEP_SENT_SECONDS);
        return $counts;
    }

    /**
     * Sends the given events now, due or not, keeping the per-order and
     * per-customer order.
     */
    public function sendNow(array $ids)
    {
        if (empty($ids)) {
            return [];
        }
        return $this->_send($this->_outbox->findPending(count($ids), $ids));
    }

    private function _send(array $rows)
    {
        $counts = ["sent" => 0, "retry" => 0, "failed" => 0, "waiting" => 0];
        $heldStores = [];
        $waitingSubjects = [];

        foreach ($rows as $row) {
            $subject = $row["subject"];
            if (
                isset($heldStores[$row["store_id"]]) ||
                isset($waitingSubjects[$subject]) ||
                $this->_outbox->hasEarlierPending($row) ||
                !$this->_outbox->claim($row["id"])
            ) {
                $waitingSubjects[$subject] = true;
                $counts["waiting"]++;
                continue;
            }

            $row = $this->_outbox->load($row["id"]);
            $outcome = $this->_sendOne($row);
            $counts[$outcome]++;

            if ($outcome === "retry") {
                $heldStores[$row["store_id"]] = true;
                $waitingSubjects[$subject] = true;
            }
        }

        return $counts;
    }

    private function _sendOne(array $row)
    {
        list($connection) = $this->_config->getClientForStore($row["store_id"]);
        if (!$connection) {
            $this->_outbox->markFailed($row, null, "LoyaltyLion is not configured for this store");
            return "failed";
        }

        try {
            list($method, $path, $json) = $this->_buildRequest($row);
        } catch (NoSuchEntityException $e) {
            $this->_outbox->markFailed($row, null, "The order no longer exists in Magento");
            return "failed";
        } catch (\Throwable $e) {
            $this->_outbox->markFailed($row, null, "Could not build the request: " . $e->getMessage());
            $this->_logger->error(
                "[LoyaltyLion] Could not build event " . $row["id"] . ": " . $e->getMessage()
            );
            return "failed";
        }

        $status = null;
        $body = "";
        try {
            $response = $connection->sendJson($method, $path, $json, $this->_timeout);
            $status = (int) $response->status;
            $body = (string) $response->body;
        } catch (\Throwable $e) {
            $body = $e->getMessage();
        }

        switch (OutboxPolicy::classify($status, $body)) {
            case OutboxPolicy::SENT:
                $this->_outbox->markSent($row, $status);
                return "sent";

            case OutboxPolicy::RETRY:
                if (OutboxPolicy::shouldGiveUp(strtotime($row["created_at"] . " UTC"), time())) {
                    $this->_outbox->markFailed($row, $status, "Gave up retrying: " . $body);
                    $this->_logger->error(
                        "[LoyaltyLion] Gave up sending event " . $row["id"] . " (" . $row["kind"] . ")"
                    );
                    return "failed";
                }
                $next = time() + OutboxPolicy::backoffSeconds((int) $row["attempts"] + 1);
                $this->_outbox->markRetry($row, $status, $body, $next);
                $this->_outbox->holdStore($row["store_id"], $next);
                $this->_logger->warning(
                    "[LoyaltyLion] Will retry event " . $row["id"] . " (" . $row["kind"] .
                        ") - status: " . ($status === null ? "none" : $status) . ", error: " . $body
                );
                return "retry";

            default:
                $this->_outbox->markFailed($row, $status, $body);
                $this->_logger->error(
                    "[LoyaltyLion] Failed to send event " . $row["id"] . " (" . $row["kind"] .
                        ") - status: " . $status . ", error: " . $body
                );
                return "failed";
        }
    }

    private function _buildRequest(array $row)
    {
        switch ($row["kind"]) {
            case Outbox::KIND_SIGNUP:
                return ["POST", "/activities", $row["payload"]];

            case Outbox::KIND_ORDER_CREATE:
                return ["POST", "/orders", $row["payload"]];

            case Outbox::KIND_ORDER_UPDATE:
                $orderId = substr($row["subject"], strlen("order:"));
                $order = $this->_orderRepository->get($orderId);
                $data = array_merge(
                    ["ip_address" => $order->getRemoteIp()],
                    $this->_orderTools->getPaymentStatus($order),
                    $this->_orderTools->getCancellationStatus($order),
                    $this->_orderTools->getOrderMetadata($order),
                    $this->_orderTools->getRefundStatus($order)
                );
                return ["PUT", "/orders/" . $orderId, json_encode($data)];

            case Outbox::KIND_CUSTOMER_EMAIL:
                return ["PUT", "/magento/customers/" . $this->_customerId($row) . "/email", $row["payload"]];

            case Outbox::KIND_CUSTOMER_DELETE:
                return ["DELETE", "/magento/customers/" . $this->_customerId($row), ""];

            case Outbox::KIND_NEWSLETTER_SIGNUP:
                return ["POST", "/magento/customers/" . $this->_customerId($row) . "/newsletter_signup", ""];
        }

        throw new \Exception("Unknown event kind " . $row["kind"]);
    }

    private function _customerId(array $row)
    {
        return rawurlencode(substr($row["subject"], strlen("customer:")));
    }
}
