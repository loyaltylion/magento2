<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Block;

use Loyaltylion\Core\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * The product on its page as the SDK's Points for product block reads it: its
 * id, and the id, price and options of each item a shopper can pick. Amounts
 * are in the smallest unit of the store's base currency, as in the SDK's cart
 * (see SdkCart). The block itself is placed through LoyaltyLion's Theme Editor.
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

        return [
            'id' => (string) $product->getId(),
            'sku' => (string) $product->getSku(),
            'price' => self::price($product),
            'variants' => $type instanceof Configurable
                ? $this->variantsOf($product, $type)
                : [self::variant($product, [])],
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

            $variants[] = self::variant($child, $options);
        }

        return $variants;
    }

    private static function variant(Product $product, array $options): array
    {
        return [
            'id' => (string) $product->getId(),
            'sku' => (string) $product->getSku(),
            'price' => self::price($product),
            'available' => (bool) $product->isSalable(),
            // an object even when empty, as the SDK reads it
            'options' => (object) $options,
        ];
    }

    // The final price the page shows, before it converts it to the shopper's display currency.
    private static function price(Product $product): int
    {
        $amount = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();

        return (int) round((float) $amount * 100);
    }
}
