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
 * The line carries the claim as an item option, as "kind:id" (see
 * ClaimedFreeProducts for the two kinds). LoyaltyLion says whether the claim
 * may be added, and the order that holds the line reports the claim back,
 * which marks it as used. The line uses no coupon, so the shopper's own
 * discount code still applies to the rest of the cart.
 */
class FreeProduct
{
    public const OPTION_CODE = 'loyaltylion_reward';

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
    public function addToCart(Quote $quote, $customerId, string $kind, string $id): array
    {
        $id = strtoupper(trim($id));
        if ($id === '') {
            return $this->refused('not_found');
        }
        $claim = $kind . ':' . $id;

        if ($this->findLine($quote, $claim)) {
            return ['added' => true, 'reason' => null];
        }

        if ($this->codeIsSpent($kind, $id)) {
            return $this->refused('used');
        }

        $answer = $this->claims->find($quote->getStore()->getWebsiteId(), $customerId, $kind, $id);
        if ($answer === null) {
            return $this->refused('unreachable');
        }
        if ($answer['outcome'] !== 'available') {
            return $this->refused((string) $answer['reason']);
        }
        if (!$this->meetsRequirement($quote, $answer)) {
            return $this->refused('cart_requirement');
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
        $product->addCustomOption(self::OPTION_CODE, $claim);
        $product->addCustomOption(
            'additional_options',
            $this->serializer->serialize([['label' => __('Reward'), 'value' => __('Free with your points')]])
        );

        try {
            $item = $quote->addProduct($product, new DataObject(['qty' => 1, self::OPTION_CODE => $claim]));
        } catch (LocalizedException $e) {
            return $this->refused('out_of_stock');
        }
        if (is_string($item)) {
            return $this->refused('out_of_stock');
        }

        $this->makeFree($item);
        $this->releaseCoupon($quote, $claim);
        $quote->collectTotals();
        $this->quotes->save($quote);

        return ['added' => true, 'reason' => null];
    }

    /**
     * Holds every reward line at one unit and no charge, and takes a code
     * reward's code off the cart as a coupon: the same claim must not also
     * discount a unit the shopper pays for.
     */
    public function hold(Quote $quote): void
    {
        foreach ($quote->getAllVisibleItems() as $item) {
            $claim = $this->claimOf($item);
            if ($claim === null) {
                continue;
            }
            if ((float) $item->getQty() !== 1.0) {
                $item->setQty(1);
            }
            $this->makeFree($item);
            $this->releaseCoupon($quote, $claim);
        }
    }

    public function hasLines(Quote $quote): bool
    {
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($this->claimOf($item) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Takes out of the cart the reward lines LoyaltyLion no longer allows, or
     * whose cart requirement the rest of the cart no longer meets, and says
     * whether it removed any. A line is kept when LoyaltyLion can't be
     * reached: it allowed the line when it was added, and an unreachable
     * service must not stop a checkout.
     */
    public function removeRefusedLines(Quote $quote): bool
    {
        $removed = false;
        foreach ($quote->getAllVisibleItems() as $item) {
            $claim = $this->claimOf($item);
            if ($claim === null) {
                continue;
            }
            [$kind, $id] = explode(':', $claim, 2);
            if ($this->codeIsSpent($kind, $id)) {
                $quote->removeItem($item->getId());
                $removed = true;
                continue;
            }
            $answer = $this->claims->find($quote->getStore()->getWebsiteId(), $quote->getCustomerId(), $kind, $id);
            if ($answer === null) {
                continue;
            }
            if ($answer['outcome'] !== 'available' || !$this->meetsRequirement($quote, $answer)) {
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
     * Takes the reward lines with these ids out of the cart, for the SDK,
     * which removes a line whose claim has ended or whose cart requirement is
     * no longer met. A line that is not a reward is left alone.
     *
     * @param string[] $itemIds
     */
    public function removeLines(Quote $quote, array $itemIds): void
    {
        $removed = false;
        foreach ($quote->getAllVisibleItems() as $item) {
            if (in_array((string) $item->getId(), $itemIds, true) && $this->claimOf($item) !== null) {
                $quote->removeItem($item->getId());
                $removed = true;
            }
        }

        if ($removed) {
            $quote->collectTotals();
            $this->quotes->save($quote);
        }
    }

    /**
     * Carries each reward line's claim and its "Reward" label from the cart
     * onto the order's lines. The label is so the merchant sees why a line has
     * no price on the order, invoice and emails. The claim is what the order
     * reports to LoyaltyLion.
     *
     * Magento copies a line's buy request to the order, and that also holds
     * the claim, but a shopper can put anything in the buy request of a line
     * they add themselves. The cart line's own option is set only here.
     */
    public function markOrderLines(OrderInterface $order, Quote $quote): void
    {
        foreach ($order->getAllItems() as $orderItem) {
            $quoteItem = $quote->getItemById($orderItem->getQuoteItemId());
            $claim = $quoteItem ? $this->claimOf($quoteItem) : null;
            if ($claim === null) {
                continue;
            }
            $options = $orderItem->getProductOptions() ?: [];
            $options[self::OPTION_CODE] = $claim;
            $label = $quoteItem->getOptionByCode('additional_options');
            if ($label) {
                $options['additional_options'] = $this->serializer->unserialize($label->getValue());
            }
            $orderItem->setProductOptions($options);
        }
    }

    /**
     * Removes the store's coupon for each code reward an order used as a free
     * line. LoyaltyLion also created the claim's code as a coupon, for stores
     * that don't add the product to the cart, and that coupon is still unused.
     */
    public function retireCoupons(OrderInterface $order): void
    {
        foreach ($this->claimsOnOrder($order, ClaimedFreeProducts::CODE) as $code) {
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
     * @param string $kind ClaimedFreeProducts::SESSION or ::CODE
     * @return string[] the ids of the claims of that kind on an order's free lines
     */
    public function claimsOnOrder(OrderInterface $order, string $kind): array
    {
        $ids = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $claim = (string) $item->getProductOptionByCode(self::OPTION_CODE);
            if (str_starts_with($claim, $kind . ':')) {
                $ids[] = substr($claim, strlen($kind) + 1);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return string|null the cart session a line holds, for a seamless free product
     */
    public function sessionOf(Item $item): ?string
    {
        $claim = (string) $this->claimOf($item);
        $prefix = ClaimedFreeProducts::SESSION . ':';

        return str_starts_with($claim, $prefix) ? substr($claim, strlen($prefix)) : null;
    }

    private function meetsRequirement(Quote $quote, array $answer): bool
    {
        $paidCost = 0.0;
        $paidQuantity = 0;
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($this->claimOf($item) !== null) {
                continue;
            }
            $paidCost += (float) $item->getBaseRowTotal() - (float) $item->getBaseDiscountAmount();
            if ((float) $item->getBaseCalculationPrice() > 0) {
                $paidQuantity += (int) $item->getQty();
            }
        }

        return CartRequirement::met($answer['minimum_requirements'] ?? null, $paidCost, $paidQuantity);
    }

    /**
     * Whether a code reward's code has already been on an order here.
     * LoyaltyLion creates each code as a coupon in the store, and
     * `retireCoupons` deletes it when an order with its free line is placed,
     * which is sooner than LoyaltyLion hears of that order. A reward line
     * never goes through the coupon, so Magento's own limit on a coupon's
     * uses can't stop a second order.
     */
    private function codeIsSpent(string $kind, string $id): bool
    {
        if ($kind !== ClaimedFreeProducts::CODE) {
            return false;
        }

        return !$this->couponFactory->create()->loadByCode($id)->getId();
    }

    private function claimOf(Item $item): ?string
    {
        $option = $item->getOptionByCode(self::OPTION_CODE);

        return $option && $option->getValue() !== null ? (string) $option->getValue() : null;
    }

    private function findLine(Quote $quote, string $claim): ?Item
    {
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($this->claimOf($item) === $claim) {
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

    private function releaseCoupon(Quote $quote, string $claim): void
    {
        if (ClaimedFreeProducts::CODE . ':' . strtoupper((string) $quote->getCouponCode()) === $claim) {
            $quote->setCouponCode('');
        }
    }

    private function refused(string $reason): array
    {
        return ['added' => false, 'reason' => $reason];
    }
}
