<?php

namespace Loyaltylion\Core\Model\Observer;


use Loyaltylion\Core\Helper\OrderTools;
use Loyaltylion\Core\Helper\Referrals;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;


class OrderCreateObserver implements ObserverInterface
{

    private $_client, $_config, $_referrals, $_telemetry, $_orderTools, $_logger;

    public function __construct(
        \Loyaltylion\Core\Helper\Client $client,
        \Loyaltylion\Core\Helper\Config $config,
        \Loyaltylion\Core\Helper\Referrals $referrals,
        \Loyaltylion\Core\Helper\Telemetry $telemetry,
        \Loyaltylion\Core\Helper\OrderTools $orderTools,
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->_client = $client;
        $this->_config = $config;
        $this->_referrals = $referrals;
        $this->_telemetry = $telemetry;
        $this->_orderTools = $orderTools;
        $this->_logger = $logger;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        # We can't track an order without a merchant_id
        if (!$order || !$order->getId()) return;

        if (!$this->_config->isEnabledForStore($order->getStoreId())) return;
        
        $this->_logger->debug("Order", ['order' => $order->toArray()]);


        $data = array(
            'merchant_id' => $order->getId(),
            'customer_id' => $order->getCustomerId(),
            'customer_email' => $order->getCustomerEmail(),
            'total' => (string)$order->getBaseGrandTotal(),
            'total_shipping' => (string)$order->getBaseShippingAmount(),
            'number' => (string)$order->getIncrementId(),
            'guest' => (bool)$order->getCustomerIsGuest(),
            'ip_address' => $order->getRemoteIp(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
        );

        $data = array_merge($data, $this->_orderTools->getOrderMetadata($order));

        $data = array_merge($data, $this->_orderTools->getPaymentStatus($order));

        if ($order->getCouponCode()) {
            $data['discount_codes'] = array(
                array(
                    'code' => $order->getCouponCode(),
                    'amount' => abs($order->getDiscountAmount()),
                ),
            );
        }


        if ($this->_referrals->getLoyaltyLionReferralId())
            $data['referral_id'] = $this->_referrals->getLoyaltyLionReferralId();

        $tracking_id = $this->_referrals->getTrackingIdFromSession();

        if ($tracking_id)
            $data['tracking_id'] = $tracking_id;

        $client = $this->_client->getClient($this->_config->getToken(), $this->_config->getSecret());
        $response = $client->orders->create($data);

        if ($response->success) {
            $this->_logger->debug('[LoyaltyLion] Tracked order OK');
        } else {
            $this->_logger->error('[LoyaltyLion] Failed to track order - status: ' . $response->status . ', error: ' . $response->error);
        }
    }
}
