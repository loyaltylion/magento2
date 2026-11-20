<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Reward;

use Loyaltylion\Core\Model\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\SalesRule\Model\CouponFactory;
use Psr\Log\LoggerInterface;

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
    public const OPTION_CODE = 'loyaltylion_reward_code';

    /** Product types that go in a cart with no choice left for the shopper to make */
    private const PRODUCT_TYPES = ['simple', 'virtual', 'downloadable'];

    public function __construct(
        private readonly Config $config,
        private readonly ClaimedFreeProducts $claims,
        private readonly ProductRepositoryInterface $products,
        private readonly CartRepositoryInterface $quotes,
        private readonly CouponFactory $couponFactory,
        private readonly Json $serializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int|string|null $websiteId null reads the website of the current request
     */
    public function isOn($websiteId = null): bool
    {
        return $this->config->forWebsite($websiteId)->addsFreeProductsToCart;
    }

    /**
     * Adds the claim's product to the cart at no charge.
     *
     * @param int|string|null $customerId
     * @return array ["added" => bool, "reason" => string|null]
     */
    public function addToCart(Quote $quote, $customerId, string $code): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return $this->refused('not_found');
        }

        if ($this->findLine($quote, $code)) {
            return ['added' => true, 'reason' => null];
        }

        $answer = $this->claims->find($quote->getStore()->getWebsiteId(), $customerId, $code);
        if ($answer === null) {
            return $this->refused('unreachable');
        }
        if ($answer['outcome'] !== 'available') {
            return $this->refused((string) $answer['reason']);
        }

        try {
            $product = $this->products->get($answer['sku'], false, $quote->getStoreId(), true);
        } catch (NoSuchEntityException $e) {
            return $this->refused('product_not_found');
        }

        if (!in_array($product->getTypeId(), self::PRODUCT_TYPES, true)
            || $product->getTypeInstance()->hasRequiredOptions($product)
        ) {
            return $this->refused('product_needs_options');
        }

        // An option only this line has keeps Magento from merging it into a
        // line of the same product that the shopper is paying for.
        $product->addCustomOption(self::OPTION_CODE, $code);
        $product->addCustomOption(
            'additional_options',
            $this->serializer->serialize([['label' => __('Reward'), 'value' => __('Free with your points')]])
        );

        try {
            $item = $quote->addProduct($product, new DataObject(['qty' => 1, self::OPTION_CODE => $code]));
        } catch (LocalizedException $e) {
            return $this->refused('out_of_stock');
        }
        if (is_string($item)) {
            return $this->refused('out_of_stock');
        }

        $this->makeFree($item);
        $this->releaseCoupon($quote, $code);
        $quote->collectTotals();
        $this->quotes->save($quote);

        return ['added' => true, 'reason' => null];
    }

    /**
     * Holds every reward line at one unit and no charge, and takes a reward's
     * code off the cart as a coupon: the same claim must not also discount a
     * unit the shopper pays for.
     */
    public function hold(Quote $quote): void
    {
        foreach ($quote->getAllVisibleItems() as $item) {
            $code = $this->codeOf($item);
            if ($code === null) {
                continue;
            }
            if ((float) $item->getQty() !== 1.0) {
                $item->setQty(1);
            }
            $this->makeFree($item);
            $this->releaseCoupon($quote, $code);
        }
    }

    /**
     * Takes out of the cart the reward lines LoyaltyLion no longer allows, and
     * says whether it removed any. A line is kept when LoyaltyLion can't be
     * reached: it allowed the line when it was added, and an unreachable
     * service must not stop a checkout.
     */
    public function removeRefusedLines(Quote $quote): bool
    {
        $removed = false;
        foreach ($quote->getAllVisibleItems() as $item) {
            $code = $this->codeOf($item);
            if ($code === null) {
                continue;
            }
            $answer = $this->claims->find($quote->getStore()->getWebsiteId(), $quote->getCustomerId(), $code);
            if ($answer !== null && $answer['outcome'] !== 'available') {
                $quote->removeItem($item->getId());
                $removed = true;
            }
        }

        if ($removed) {
            $quote->collectTotals();
            $this->quotes->save($quote);
        }

        return $removed;
    }

    /**
     * Carries the "Reward" label from the cart onto the order's lines, so the
     * merchant sees why a line has no price on the order, invoice and emails.
     * Magento copies a line's buy request to the order but not this label.
     */
    public function labelOrderLines(OrderInterface $order, Quote $quote): void
    {
        foreach ($order->getAllItems() as $orderItem) {
            $quoteItem = $quote->getItemById($orderItem->getQuoteItemId());
            $label = $quoteItem ? $quoteItem->getOptionByCode('additional_options') : null;
            if (!$quoteItem || !$label || $this->codeOf($quoteItem) === null) {
                continue;
            }
            $options = $orderItem->getProductOptions() ?: [];
            $options['additional_options'] = $this->serializer->unserialize($label->getValue());
            $orderItem->setProductOptions($options);
        }
    }

    /**
     * Removes the store's coupon for each claim an order used as a free line.
     * LoyaltyLion also created the claim's code as a coupon, for stores that
     * don't add the product to the cart, and that coupon is still unused.
     */
    public function retireCoupons(OrderInterface $order): void
    {
        foreach ($this->codesOnOrder($order) as $code) {
            try {
                $coupon = $this->couponFactory->create()->loadByCode($code);
                if ($coupon->getId()) {
                    $coupon->delete();
                }
            } catch (\Exception $e) {
                $this->logger->error(
                    '[LoyaltyLion] Could not remove the coupon of a used free product: ' . $e->getMessage()
                );
            }
        }
    }

    /**
     * @return string[] the claim codes of the free lines on an order
     */
    public function codesOnOrder(OrderInterface $order): array
    {
        $codes = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $request = $item->getProductOptionByCode('info_buyRequest');
            if (is_array($request) && !empty($request[self::OPTION_CODE])) {
                $codes[] = (string) $request[self::OPTION_CODE];
            }
        }

        return array_values(array_unique($codes));
    }

    private function codeOf(Item $item): ?string
    {
        $option = $item->getOptionByCode(self::OPTION_CODE);

        return $option && $option->getValue() !== null ? (string) $option->getValue() : null;
    }

    private function findLine(Quote $quote, string $code): ?Item
    {
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($this->codeOf($item) === $code) {
                return $item;
            }
        }

        return null;
    }

    private function makeFree(Item $item): void
    {
        $item->setCustomPrice(0);
        $item->setOriginalCustomPrice(0);
        // Without this Magento recalculates the price from the catalog.
        $item->getProduct()->setIsSuperMode(true);
    }

    private function releaseCoupon(Quote $quote, string $code): void
    {
        if (strtoupper((string) $quote->getCouponCode()) === $code) {
            $quote->setCouponCode('');
        }
    }

    private function refused(string $reason): array
    {
        return ['added' => false, 'reason' => $reason];
    }
}
