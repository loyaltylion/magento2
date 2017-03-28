<?php

namespace Loyaltylion\Core\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;


class OrderUpdateObserver implements ObserverInterface {

    public function __construct(
        \Loyaltylion\Core\Helper\Client $client,
        \Loyaltylion\Core\Helper\Config $config,
        \Loyaltylion\Core\Helper\Telemetry $telemetry,
        \Loyaltylion\Core\Helper\OrderTools $orderTools,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_client = $client;
        $this->_config = $config;
        $this->_telemetry = $telemetry;
        $this->_orderTools = $orderTools;
        $this->_logger = $logger;
    }

    public function execute(Observer $observer)
    {
        if (!$this->_config->isEnabled()) return;

        $order = $observer->getEvent()->getOrder();
        if (!$order) return;

        $data = array(
            'refund_status' => 'not_refunded',
            'total_refunded' => 0,
            'ip_address' => $order->getRemoteIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        );

        $data = array_merge($data, $this->_orderTools->getPaymentStatus($order));

        $data['cancellation_status'] = $order->getState() == 'canceled' ? 'cancelled' : 'not_cancelled';

        $total_refunded = $order->getBaseTotalRefunded();

        if ($total_refunded > 0) {
            if ($total_refunded < $order->getBaseGrandTotal()) {
                $data['refund_status'] = 'partially_refunded';
                $data['total_refunded'] = $total_refunded;
            } else {
                // assume full refund. this should be fine as magento appears to only allow refunding up to
                // the amount paid
                $data['refund_status'] = 'refunded';
                $data['total_refunded'] = $order->getBaseGrandTotal();
            }
        }

        $data = array_merge($data, $this->_orderTools->getOrderMetadata($order));

        $response = $this->_client->orders->update($order->getId(), $data);

        if ($response->success) {
            $this->_logger->debug('[LoyaltyLion] Updated order OK');
        } else if ($response->status != 404) {
            // sometimes this will get fired before the order has been created, so we'll get a 404 back - no reason to
            // error, because this is expected behaviour
            $this->logger->error('[LoyaltyLion] Failed to update order - status: ' . $response->status . ', error: ' . $response->error);
        }
    }
}