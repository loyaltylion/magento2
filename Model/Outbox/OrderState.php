<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Outbox;

use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * An order as the store holds it now, in Magento's own field names and
 * base-currency amounts. LoyaltyLion works out what it means (paid, refunded,
 * cancelled), so nothing here interprets the order.
 */
class OrderState
{
    public function __construct(private readonly FreeProduct $freeProduct)
    {
    }

    public function of(OrderInterface $order): array
    {
        $state = [
            'entity_id' => (int) $order->getEntityId(),
            'increment_id' => (string) $order->getIncrementId(),
            'state' => (string) $order->getState(),
            'created_at' => (string) $order->getCreatedAt(),
            'customer_id' => $order->getCustomerIsGuest() ? null : $this->integer($order->getCustomerId()),
            'customer_email' => (string) $order->getCustomerEmail(),
            'customer_group_id' => $this->integer($order->getCustomerGroupId()),
            'base_grand_total' => $this->amount($order->getBaseGrandTotal()) ?? '0',
            'base_shipping_amount' => $this->amount($order->getBaseShippingAmount()) ?? '0',
            'base_total_due' => $this->amount($order->getBaseTotalDue()),
            'base_total_paid' => $this->amount($order->getBaseTotalPaid()),
            'base_total_refunded' => $this->amount($order->getBaseTotalRefunded()),
            'coupon_code' => $order->getCouponCode() ?: null,
            'base_discount_amount' => $this->amount($order->getBaseDiscountAmount()),
            'remote_ip' => $order->getRemoteIp() ?: null,
            // Free products added at no charge use no coupon, so their claims
            // are not in coupon_code.
            'reward_codes' => $this->freeProduct->codesOnOrder($order) ?: null,
        ];

        // Magento's own API leaves a field out rather than sending null, and
        // LoyaltyLion reads this the same way.
        return array_filter($state, static fn ($value) => $value !== null);
    }

    private function amount($value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function integer($value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
