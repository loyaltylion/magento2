<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Block;

use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Catalog\Model\Product;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * The points a shopper earns for the product on its page. The SDK works them
 * out from the product id and the price this block puts on the page.
 */
class ProductPoints extends Template
{
    /**
     * Types whose page price is what one of the product costs. A grouped or
     * bundle product's page price is a "from" price, not the price paid.
     */
    private const PRICED_TYPES = ['simple', 'virtual', 'downloadable', 'configurable'];

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly Placements $placements,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getProduct(): ?Product
    {
        if (!$this->placements->isOn(Placements::PRODUCT_POINTS)) {
            return null;
        }

        $product = $this->registry->registry('product');

        return $product && in_array($product->getTypeId(), self::PRICED_TYPES, true) ? $product : null;
    }

    /** The final price as the page shows it, in the shopper's display currency. */
    public function getDisplayPrice(Product $product): string
    {
        $amount = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();

        return number_format((float) $this->priceCurrency->convert($amount), 2, '.', '');
    }
}
