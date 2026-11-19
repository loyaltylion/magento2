<?php

namespace Loyaltylion\Core\Model\Reward;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

/**
 * A claimed free product as a line in the shopper's cart at no charge.
 *
 * The line carries the claim's code as an item option. LoyaltyLion says
 * whether the code may be added, and the order that holds the line reports
 * the code back, which marks the claim as used. The line uses no coupon, so
 * the shopper's own discount code still applies to the rest of the cart.
 */
class FreeProduct
{
    const OPTION_CODE = "loyaltylion_reward_code";
    const SETTING = "loyaltylion_core/storefront/free_product_cart";

    /** Product types that go in a cart with no choice left for the shopper to make */
    const PRODUCT_TYPES = ["simple", "virtual", "downloadable"];

    private $_config;
    private $_productRepository;
    private $_quoteRepository;
    private $_couponFactory;
    private $_serializer;
    private $_scopeConfig;
    private $_logger;

    public function __construct(
        \Loyaltylion\Core\Helper\Config $config,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\SalesRule\Model\CouponFactory $couponFactory,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_config = $config;
        $this->_productRepository = $productRepository;
        $this->_quoteRepository = $quoteRepository;
        $this->_couponFactory = $couponFactory;
        $this->_serializer = $serializer;
        $this->_scopeConfig = $scopeConfig;
        $this->_logger = $logger;
    }

    public function isOn($storeId = null)
    {
        return $this->_scopeConfig->isSetFlag(
            self::SETTING,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Adds the claim's product to the cart at no charge.
     *
     * @return array ["added" => bool, "reason" => string|null]
     */
    public function addToCart(Quote $quote, $customerId, $code)
    {
        $code = strtoupper(trim((string) $code));
        if ($code === "") {
            return $this->_refused("not_found");
        }

        if ($this->findLine($quote, $code)) {
            return ["added" => true, "reason" => null];
        }

        $answer = $this->ask($quote->getStoreId(), $customerId, $code);
        if ($answer === null) {
            return $this->_refused("unreachable");
        }
        if ($answer["outcome"] !== "available") {
            return $this->_refused($answer["reason"]);
        }

        try {
            $product = $this->_productRepository->get(
                $answer["sku"],
                false,
                $quote->getStoreId(),
                true
            );
        } catch (NoSuchEntityException $e) {
            return $this->_refused("product_not_found");
        }

        if (
            !in_array($product->getTypeId(), self::PRODUCT_TYPES) ||
            $product->getTypeInstance()->hasRequiredOptions($product)
        ) {
            return $this->_refused("product_needs_options");
        }

        // An option only this line has keeps Magento from merging it into a
        // line of the same product that the shopper is paying for.
        $product->addCustomOption(self::OPTION_CODE, $code);
        $product->addCustomOption(
            "additional_options",
            $this->_serializer->serialize([
                ["label" => __("Reward"), "value" => __("Free with your points")],
            ])
        );

        try {
            $item = $quote->addProduct(
                $product,
                new DataObject(["qty" => 1, self::OPTION_CODE => $code])
            );
        } catch (LocalizedException $e) {
            return $this->_refused("out_of_stock");
        }
        if (is_string($item)) {
            return $this->_refused("out_of_stock");
        }

        $this->_makeFree($item);
        $this->_releaseCoupon($quote, $code);
        $quote->collectTotals();
        $this->_quoteRepository->save($quote);

        return ["added" => true, "reason" => null];
    }

    /**
     * Holds every reward line at one unit and no charge, and takes a reward's
     * code off the cart as a coupon: the same claim must not also discount a
     * unit the shopper pays for.
     */
    public function enforce(Quote $quote)
    {
        foreach ($quote->getAllVisibleItems() as $item) {
            $code = $this->codeOf($item);
            if ($code === null) {
                continue;
            }
            if ((float) $item->getQty() !== 1.0) {
                $item->setQty(1);
            }
            $this->_makeFree($item);
            $this->_releaseCoupon($quote, $code);
        }
    }

    /**
     * Takes out of the cart the reward lines LoyaltyLion no longer allows, and
     * says whether it removed any. A line is kept when LoyaltyLion can't be
     * reached: it allowed the line when it was added, and an unreachable
     * service must not stop a checkout.
     */
    public function removeRefusedLines(Quote $quote)
    {
        $removed = false;
        foreach ($quote->getAllVisibleItems() as $item) {
            $code = $this->codeOf($item);
            if ($code === null) {
                continue;
            }
            $answer = $this->ask(
                $quote->getStoreId(),
                $quote->getCustomerId(),
                $code
            );
            if ($answer !== null && $answer["outcome"] !== "available") {
                $quote->removeItem($item->getId());
                $removed = true;
            }
        }

        if ($removed) {
            $quote->collectTotals();
            $this->_quoteRepository->save($quote);
        }

        return $removed;
    }

    /**
     * Carries the "Reward" label from the cart onto the order's lines, so the
     * merchant sees why a line has no price on the order, invoice and emails.
     * Magento copies a line's buy request to the order but not this label.
     */
    public function labelOrderLines($order, Quote $quote)
    {
        foreach ($order->getAllItems() as $orderItem) {
            $quoteItem = $quote->getItemById($orderItem->getQuoteItemId());
            $label = $quoteItem
                ? $quoteItem->getOptionByCode("additional_options")
                : null;
            if (!$quoteItem || !$label || $this->codeOf($quoteItem) === null) {
                continue;
            }
            $options = $orderItem->getProductOptions() ?: [];
            $options["additional_options"] = $this->_serializer->unserialize(
                $label->getValue()
            );
            $orderItem->setProductOptions($options);
        }
    }

    /**
     * Removes the store's coupon for each claim an order used as a free line.
     * LoyaltyLion also created the claim's code as a coupon, for stores that
     * don't add the product to the cart, and that coupon is still unused.
     */
    public function retireCoupons($order)
    {
        foreach ($this->codesOnOrder($order) as $code) {
            try {
                $coupon = $this->_couponFactory->create()->loadByCode($code);
                if ($coupon->getId()) {
                    $coupon->delete();
                }
            } catch (\Exception $e) {
                $this->_logger->error(
                    "[LoyaltyLion] Could not remove the coupon of a used free product: " .
                        $e->getMessage()
                );
            }
        }
    }

    /**
     * The claim codes of the free lines on an order
     */
    public function codesOnOrder($order)
    {
        $codes = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $request = $item->getProductOptionByCode("info_buyRequest");
            if (is_array($request) && !empty($request[self::OPTION_CODE])) {
                $codes[] = (string) $request[self::OPTION_CODE];
            }
        }
        return array_values(array_unique($codes));
    }

    public function codeOf(Item $item)
    {
        $option = $item->getOptionByCode(self::OPTION_CODE);
        return $option && $option->getValue() !== null
            ? (string) $option->getValue()
            : null;
    }

    public function findLine(Quote $quote, $code)
    {
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($this->codeOf($item) === $code) {
                return $item;
            }
        }
        return null;
    }

    /**
     * LoyaltyLion's answer for a claim's code, or null when it can't be reached
     */
    public function ask($storeId, $customerId, $code)
    {
        if (!$customerId) {
            return ["outcome" => "unavailable", "reason" => "signed_out"];
        }

        try {
            list($connection) = $this->_config->getClientForStore($storeId);
            if (!$connection) {
                return null;
            }
            $response = $connection->getJson(
                "/magento/customers/" .
                    rawurlencode((string) $customerId) .
                    "/free_product_rewards/" .
                    rawurlencode($code),
                3
            );
            $answer = json_decode((string) $response->body, true);
            if (
                (int) $response->status !== 200 ||
                !is_array($answer) ||
                !isset($answer["outcome"])
            ) {
                $this->_logger->error(
                    "[LoyaltyLion] Free product check failed - status: " .
                        $response->status
                );
                return null;
            }
            return $answer;
        } catch (\Exception $e) {
            $this->_logger->error(
                "[LoyaltyLion] Free product check failed: " . $e->getMessage()
            );
            return null;
        }
    }

    private function _makeFree(Item $item)
    {
        $item->setCustomPrice(0);
        $item->setOriginalCustomPrice(0);
        // Without this Magento recalculates the price from the catalog.
        $item->getProduct()->setIsSuperMode(true);
    }

    private function _releaseCoupon(Quote $quote, $code)
    {
        if (strtoupper((string) $quote->getCouponCode()) === $code) {
            $quote->setCouponCode("");
        }
    }

    private function _refused($reason)
    {
        return ["added" => false, "reason" => $reason];
    }
}
