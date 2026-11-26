<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Block;

use Loyaltylion\Core\Model\Config;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * The product on its page as the SDK's Points for product block reads it: its
 * id, and the id, price and options of each item a shopper can pick. Prices
 * are on the base the order is sent with, as in the SDK's cart (see SdkCart):
 * the store's base currency, excluding tax, in its smallest unit. The block
 * itself is placed through LoyaltyLion's Theme Editor.
 */
class ProductData extends Template
{
    /**
     * Types whose page price is what one of the product costs. A grouped or
     * bundle product's page price is a "from" price, not the price paid.
     */
    private const PRICED_TYPES = ['simple', 'virtual', 'downloadable', 'configurable'];

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly CatalogHelper $catalogHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** The product's data, or null where the SDK isn't on the page or can't price the product. */
    public function getProductData(): ?array
    {
        if (!$this->config->forWebsite()->showsSdk()) {
            return null;
        }

        $product = $this->registry->registry('product');

        if (!$product instanceof Product || !in_array($product->getTypeId(), self::PRICED_TYPES, true)) {
            return null;
        }

        $type = $product->getTypeInstance();
        $variants = $type instanceof Configurable
            ? $this->variantsOf($product, $type)
            : [$this->variant($product, [])];

        return [
            'id' => (string) $product->getId(),
            'sku' => (string) $product->getSku(),
            'price' => $type instanceof Configurable ? $this->lowestPrice($variants) : $variants[0]['price'],
            'variants' => $variants,
        ];
    }

    private function variantsOf(Product $product, Configurable $type): array
    {
        $attributes = array_column($type->getConfigurableAttributesAsArray($product), 'attribute_code', 'attribute_id');
        $variants = [];

        foreach ($type->getUsedProducts($product) as $child) {
            $options = [];

            foreach ($attributes as $attributeId => $code) {
                $options[(string) $attributeId] = (string) $child->getData($code);
            }

            $variants[] = $this->variant($child, $options);
        }

        return $variants;
    }

    private function variant(Product $product, array $options): array
    {
        return [
            'id' => (string) $product->getId(),
            'sku' => (string) $product->getSku(),
            'price' => $this->price($product),
            'available' => (bool) $product->isSalable(),
            // an object even when empty, as the SDK reads it
            'options' => (object) $options,
        ];
    }

    /**
     * What one costs after special and catalog prices, before any cart price
     * rule. Magento's final price is in base currency and as the catalog holds
     * it, which can include tax; Magento's own helper takes the tax off.
     */
    private function price(Product $product): int
    {
        $amount = $this->catalogHelper->getTaxPrice($product, $product->getFinalPrice(1), false);

        return (int) round((float) $amount * 100);
    }

    /**
     * A configurable product's price is its cheapest choice in stock, as its
     * page shows, or its cheapest choice when none is in stock.
     */
    private function lowestPrice(array $variants): int
    {
        $available = array_filter($variants, static fn (array $variant) => $variant['available']);
        $prices = array_column($available ?: $variants, 'price');

        return $prices ? min($prices) : 0;
    }
}
