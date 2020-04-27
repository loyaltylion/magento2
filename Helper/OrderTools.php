<?php

namespace Loyaltylion\Core\Helper;

class OrderTools
{
    private $_telemetry;

    public function __construct(
        \Loyaltylion\Core\Helper\Telemetry $telemetry
    ) {
        $this->_telemetry = $telemetry;
    }

    public function getPaymentStatus($order)
    {
        $payment_data = [];
        if ($order->getBaseTotalDue() == $order->getBaseGrandTotal()) {
            $payment_data['payment_status'] = 'not_paid';
            $payment_data['total_paid'] = 0;
        } elseif ($order->getBaseTotalDue() == 0) {
            $payment_data['payment_status'] = 'paid';
            $payment_data['total_paid'] = $order->getBaseGrandTotal();
        } else {
            $payment_data['payment_status'] = 'partially_paid';
            $payment_data['total_paid'] = (float)$order->getBaseTotalPaid();
        }
        return $payment_data;
    }

    public function getOrderMetadata($order)
    {
        $metadata = [];
        $metadata['$magento_payload'] = $order->toArray();
        $metadata['$magento_payload']['order_items'] = $order->getAllItems();
        $metadata['$magento_payload']['addresses'] = $order->getAddresses();
        $metadata = array_merge($metadata, $this->_telemetry->getSystemInfo());
        return $metadata;
    }
}
