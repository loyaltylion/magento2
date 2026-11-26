<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Cart;

use Loyaltylion\Core\Model\Config;
use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

/**
 * The shopper's cart as LoyaltyLion's SDK reads one: the shape of Shopify's
 * Cart AJAX API, which is the only cart the SDK knows. Amounts are the ones
 * the order is sent with: the store's base currency, excluding tax, in its
 * smallest unit. As in Shopify's, `price` and the `original_` amounts are
 * before discounts, and `discounted_price`, `line_price` and `total_price`
 * after them. `total_price` is what the free product's cart requirement counts.
 */
class SdkCart
{
    /** The line property the SDK looks for on a seamless free product's line */
    public const SESSION_PROPERTY = '__lion_sfp_id';

    public function __construct(
        private readonly FreeProduct $freeProduct,
        private readonly Config $config
    ) {
    }

    /**
     * Whether the SDK may read the cart: wherever the website shows the SDK,
     * whether or not it adds free products to the cart.
     *
     * @return bool
     */
    public function isServed(): bool
    {
        return $this->config->forWebsite()->showsSdk();
    }

    public function of(Quote $quote): array
    {
        $items = array_map(fn (Item $item) => $this->line($item), array_values($quote->getAllVisibleItems()));
        $coupon = (string) $quote->getCouponCode();

        return [
            // The SDK takes a cart only with a token; a shopper who has put
            // nothing in a cart has no quote yet.
            'token' => $quote->getId() ? (string) $quote->getId() : 'empty',
            'note' => '',
            'attributes' => new \stdClass(),
            'original_total_price' => array_sum(array_column($items, 'original_line_price')),
            'total_price' => array_sum(array_column($items, 'line_price')),
            'total_discount' => array_sum(array_column($items, 'total_discount')),
            // Magento keeps a coupon on the cart only while it applies.
            'discount_codes' => $coupon === '' ? [] : [['code' => $coupon, 'applicable' => true]],
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
        $rowTotal = (float) $item->getBaseRowTotal();
        $discountedRowTotal = $rowTotal - (float) $item->getBaseDiscountAmount();
        $price = $quantity > 0 ? self::cents($rowTotal / $quantity) : 0;

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
            'discounted_price' => $quantity > 0 ? self::cents($discountedRowTotal / $quantity) : 0,
            'line_price' => self::cents($discountedRowTotal),
            'original_line_price' => self::cents($rowTotal),
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
