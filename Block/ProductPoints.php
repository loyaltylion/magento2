<?php

namespace Loyaltylion\Core\Block;

/**
 * The points a shopper earns for the product on its page. The SDK works the
 * points out from the product id and the price this block puts on the page.
 */
class ProductPoints extends \Magento\Framework\View\Element\Template
{
    /**
     * Types whose page price is what one of the product costs. A grouped or
     * bundle product's page price is a "from" price, not the price paid.
     */
    const PRICED_TYPES = ["simple", "virtual", "downloadable", "configurable"];

    private $_registry;
    private $_priceCurrency;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_registry = $registry;
        $this->_priceCurrency = $priceCurrency;
    }

    public function getProduct()
    {
        $product = $this->_registry->registry("product");

        if (!$product || !in_array($product->getTypeId(), self::PRICED_TYPES, true)) {
            return null;
        }

        return $product;
    }

    /**
     * The final price as the page shows it, in the shopper's display currency
     */
    public function getDisplayPrice($product)
    {
        $amount = $product->getPriceInfo()->getPrice("final_price")->getAmount()->getValue();

        return number_format($this->_priceCurrency->convert($amount), 2, ".", "");
    }
}
