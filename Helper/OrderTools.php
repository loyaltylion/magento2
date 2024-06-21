<?php

namespace Loyaltylion\Core\Helper;

class OrderTools
{
    private $_telemetry;
    private $_httpHeader;
    private $_tracking;

    public function __construct(
        \Loyaltylion\Core\Helper\Telemetry $telemetry,
        \Magento\Framework\HTTP\Header $httpHeader,
        \Loyaltylion\Core\Helper\Tracking $tracking
    ) {
        $this->_telemetry = $telemetry;
        $this->_httpHeader = $httpHeader;
        $this->_tracking = $tracking;
    }

    public function getPaymentStatus($order)
    {
        $payment_data = [];
        if ($order->getBaseTotalDue() == $order->getBaseGrandTotal()) {
            $payment_data["payment_status"] = "not_paid";
            $payment_data["total_paid"] = 0;
        } elseif ($order->getBaseTotalDue() == 0) {
            $payment_data["payment_status"] = "paid";
            $payment_data["total_paid"] = $order->getBaseGrandTotal();
        } else {
            $payment_data["payment_status"] = "partially_paid";
            $payment_data["total_paid"] = (float) $order->getBaseTotalPaid();
        }
        return $payment_data;
    }

    public function getCancellationStatus($order)
    {
        $status =
            $order->getState() == "canceled" ? "cancelled" : "not_cancelled";
        return ["cancellation_status" => $status];
    }

    public function getOrderMetadata($order)
    {
        $metadata = [];
        $metadata['$magento_payload'] = $order->toArray();
        $metadata['$magento_payload']["order_items"] = $order->getAllItems();
        $metadata['$magento_payload']["addresses"] = $order->getAddresses();
        $metadata = array_merge($metadata, $this->_telemetry->getSystemInfo());
        return $metadata;
    }

    public function getRefundStatus($order)
    {
        $refund_data = [
            "refund_status" => "not_refunded",
            "total_refunded" => 0,
        ];

        $total_refunded = $order->getBaseTotalRefunded();

        if ($total_refunded > 0) {
            if ($total_refunded < $order->getBaseGrandTotal()) {
                $refund_data["refund_status"] = "partially_refunded";
                $refund_data["total_refunded"] = $total_refunded;
            } else {
                // assume full refund. this should be fine as magento appears
                // to only allow refunding up to the amount paid
                $refund_data["refund_status"] = "refunded";
                $refund_data["total_refunded"] = $order->getBaseGrandTotal();
            }
        }
        return $refund_data;
    }

    public function getOrderClientData($order)
    {
        // getTrackingData already returns an IP, but the initial IP an order was
        // placed with is recorded on the order model itself -- so we'll report that.
        return array_merge($this->_tracking->getTrackingData(), [
            "ip_address" => $order->getRemoteIp(),
        ]);
    }

    public function getDiscountCodes($order)
    {
        $code = $order->getCouponCode();
        if ($code) {
            return [
                "discount_codes" => [
                    "code" => $code,
                    "amount" => abs($order->getDiscountAmount()),
                ],
            ];
        }
        return ["discount_codes" => []];
    }

    public function getBaseOrderData($order)
    {
        return [
            "merchant_id" => $order->getId(),
            "customer_id" => $order->getCustomerId(),
            "customer_email" => $order->getCustomerEmail(),
            "total" => (string) $order->getBaseGrandTotal(),
            "total_shipping" => (string) $order->getBaseShippingAmount(),
            "number" => (string) $order->getIncrementId(),
            "guest" => (bool) $order->getCustomerIsGuest(),
        ];
    }
}
