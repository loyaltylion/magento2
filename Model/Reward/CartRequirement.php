<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Reward;

/**
 * What the rest of a cart must hold for a seamless free product to stay in
 * it, as its merchant set on the reward: a spend, or a number of paid items.
 * LoyaltyLion's SDK applies the same rule in the browser; this is the check
 * that can't be skipped.
 */
class CartRequirement
{
    /**
     * @param array|null $requirement ["cost" => float] or ["quantity" => int], or null for none
     * @param float $paidCost what the lines that are not rewards come to, after their discounts, in base
     *                        currency and excluding tax, as the order is sent
     * @param int $paidQuantity how many units those lines hold that have a price
     */
    public static function met(?array $requirement, float $paidCost, int $paidQuantity): bool
    {
        if (isset($requirement['cost'])) {
            return $paidCost >= (float) $requirement['cost'];
        }
        if (isset($requirement['quantity'])) {
            return $paidQuantity >= (int) $requirement['quantity'];
        }

        return true;
    }
}
