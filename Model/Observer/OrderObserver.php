<?php

namespace Loyaltylion\Core\Model\Observer;


use Loyaltylion\Core\Helper\Referrals;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;


class OrderObserver implements ObserverInterface {

    public function __construct(
        \Loyaltylion\Core\Helper\Client $client,
        \Loyaltylion\Core\Block\Sdk $sdk,
        \Loyaltylion\Core\Helper\Referrals $referrals,
        \Loyaltylion\Core\Helper\Telemetry $telemetry,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_client = $client;
        $this->_sdk = $sdk;
        $this->_referrals = $referrals;
        $this->_logger = $logger;
        $this->_telemetry = $telemetry;
    }

    public function execute(Observer $observer) {
        if (!$this->_sdk->isEnabled()) return;

        $order = $observer->getEvent()->getOrder();

        # We can't track an order without a merchant_id
        if (!$order || !$order->getId()) return;

        $data = array(
            'merchant_id' => $order->getId(),
            'customer_id' => $order->getCustomerId(),
            'customer_email' => $order->getCustomerEmail(),
            'total' => (string) $order->getBaseGrandTotal(),
            'total_shipping' => (string) $order->getBaseShippingAmount(),
            'number' => (string) $order->getIncrementId(),
            'guest' => (bool) $order->getCustomerIsGuest(),
            'ip_address' => $order->getRemoteIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            '$magento_payload' => $order->toArray()
        );


        $data['$magento_payload']['order_items'] = $order->getAllItems();
        $data['$magento_payload']['addresses'] = $order->getAddresses();

        $data = array_merge($data, $this->_telemetry->getSystemInfo());

        if ($order->getBaseTotalDue() == $order->getBaseGrandTotal()) {
            $data['payment_status'] = 'not_paid';
        } else if ($order->getBaseTotalDue() == 0) {
            $data['payment_status'] = 'paid';
        } else {
            $data['payment_status'] = 'partially_paid';
            $total_paid = $order->getBaseTotalPaid();
            $data['total_paid'] = $total_paid === null ? 0 : $total_paid;
        }

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

        $response = $this->_client->orders->create($data);

        if ($response->success) {
            $this->_logger->debug('[LoyaltyLion] Tracked order OK');
        } else {
            $this->_logger->error('[LoyaltyLion] Failed to track order - status: ' . $response->status . ', error: ' . $response->error);
        }
    }
}