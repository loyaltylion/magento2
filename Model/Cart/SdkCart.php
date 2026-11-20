<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Cart;

use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

/**
 * The shopper's cart as LoyaltyLion's SDK reads one: the shape of Shopify's
 * Cart AJAX API, which is the only cart the SDK knows. Amounts are in the
 * smallest unit of the store's base currency.
 */
class SdkCart
{
    /** The line property the SDK looks for on a seamless free product's line */
    public const SESSION_PROPERTY = '__lion_sfp_id';

    public function __construct(private readonly FreeProduct $freeProduct)
    {
    }

    public function of(Quote $quote): array
    {
        $items = array_map(fn (Item $item) => $this->line($item), array_values($quote->getAllVisibleItems()));
        $total = array_sum(array_column($items, 'line_price'));

        return [
            // The SDK takes a cart only with a token; a shopper who has put
            // nothing in a cart has no quote yet.
            'token' => $quote->getId() ? (string) $quote->getId() : 'empty',
            'note' => '',
            'attributes' => new \stdClass(),
            'original_total_price' => $total,
            'total_price' => $total,
            'total_discount' => array_sum(array_column($items, 'total_discount')),
            'currency' => (string) $quote->getBaseCurrencyCode(),
            'total_weight' => 0,
            'item_count' => (int) array_sum(array_column($items, 'quantity')),
            'items' => $items,
            'requires_shipping' => !$quote->isVirtual(),
        ];
    }

    private function line(Item $item): array
    {
        $session = $this->freeProduct->sessionOf($item);
        $productId = (int) $item->getProductId();
        $quantity = (int) $item->getQty();
        $price = self::cents($item->getBaseCalculationPrice());
        $linePrice = self::cents((float) $item->getBaseRowTotal() - (float) $item->getBaseDiscountAmount());

        return [
            'key' => (string) $item->getId(),
            'id' => $productId,
            'variant_id' => $productId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'title' => (string) $item->getName(),
            'product_title' => (string) $item->getName(),
            'price' => $price,
            'original_price' => $price,
            'discounted_price' => $price,
            'line_price' => $linePrice,
            'original_line_price' => $price * $quantity,
            'total_discount' => self::cents($item->getBaseDiscountAmount()),
            'discounts' => [],
            'properties' => $session === null ? null : [self::SESSION_PROPERTY => $session],
            'sku' => (string) $item->getSku(),
            'grams' => 0,
            'vendor' => '',
            'taxable' => true,
            'gift_card' => false,
            'url' => (string) $item->getProduct()->getProductUrl(),
            'image' => null,
            'handle' => (string) $item->getProduct()->getUrlKey(),
            'requires_shipping' => !$item->getIsVirtual(),
            'product_type' => (string) $item->getProductType(),
            'product_description' => '',
            'variant_title' => null,
            'variant_options' => [],
        ];
    }

    private static function cents($amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
