<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Outbox;

use Loyaltylion\Core\Model\Reward\ClaimedFreeProducts;
use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * An order as the store holds it now, in Magento's own field names and
 * base-currency amounts. LoyaltyLion works out what it means (paid, refunded,
 * cancelled), so nothing here interprets the order.
 */
class OrderState
{
    /** LoyaltyLion takes at most this many lines of one order. */
    private const MOST_ITEMS = 500;

    public function __construct(private readonly FreeProduct $freeProduct)
    {
    }

    public function of(OrderInterface $order): array
    {
        $state = [
            'entity_id' => (int) $order->getEntityId(),
            'increment_id' => (string) $order->getIncrementId(),
            'state' => (string) $order->getState(),
            'created_at' => Timestamp::utc((string) $order->getCreatedAt()),
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
            'reward_codes' => $this->freeProduct->claimsOnOrder($order, ClaimedFreeProducts::CODE) ?: null,
            'reward_sessions' => $this->freeProduct->claimsOnOrder($order, ClaimedFreeProducts::SESSION) ?: null,
            'items' => $this->items($order) ?: null,
        ];

        // Magento's own API leaves a field out rather than sending null, and
        // LoyaltyLion reads this the same way.
        return array_filter($state, static fn ($value) => $value !== null);
    }

    /**
     * The lines a shopper sees on the order. A configurable product is one
     * line: the product they chose, with the variant they got as its child.
     *
     * @return array<int, array<string, mixed>>
     */
    private function items(OrderInterface $order): array
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            // A child line repeats its parent, which carries the price.
            if ($item->getParentItemId() || count($items) >= self::MOST_ITEMS) {
                continue;
            }

            $children = $item->getChildrenItems() ?: [];
            $variant = $item->getProductType() === 'configurable' && $children ? $children[0] : null;

            $items[] = array_filter([
                'item_id' => (int) $item->getItemId(),
                'product_id' => (int) $item->getProductId(),
                'variant_id' => $variant ? (int) $variant->getProductId() : null,
                'sku' => (string) $item->getSku(),
                'name' => (string) $item->getName(),
                'qty_ordered' => (float) $item->getQtyOrdered(),
                'base_price' => $this->amount($item->getBasePrice()) ?? '0',
                'base_discount_amount' => $this->amount($item->getBaseDiscountAmount()),
                'base_tax_amount' => $this->amount($item->getBaseTaxAmount()),
            ], static fn ($value) => $value !== null);
        }

        return $items;
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
