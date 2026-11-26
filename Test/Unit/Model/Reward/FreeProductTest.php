<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model\Reward;

use Loyaltylion\Core\Model\Config;
use Loyaltylion\Core\Model\Reward\ClaimedFreeProducts;
use Loyaltylion\Core\Model\Reward\FreeProduct;
use Loyaltylion\Core\Model\Reward\RewardCoupon;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FreeProductTest extends TestCase
{
    /** @var ClaimedFreeProducts&MockObject */
    private $claims;

    /** @var ProductRepositoryInterface&MockObject */
    private $products;

    /** @var CartRepositoryInterface&MockObject */
    private $quotes;

    /** @var RewardCoupon&MockObject */
    private $rewardCoupon;

    private FreeProduct $freeProduct;

    protected function setUp(): void
    {
        $this->claims = $this->createMock(ClaimedFreeProducts::class);
        $this->products = $this->createMock(ProductRepositoryInterface::class);
        $this->quotes = $this->createMock(CartRepositoryInterface::class);
        $this->rewardCoupon = $this->createMock(RewardCoupon::class);

        $this->freeProduct = new FreeProduct(
            $this->createMock(Config::class),
            $this->claims,
            $this->products,
            $this->quotes,
            $this->rewardCoupon,
            new Json()
        );
    }

    public function testHoldKeepsARewardLineAtOneUnitAtNoChargeAndTakesItsCodeOffTheCart(): void
    {
        $reward = $this->line(1, 'code:RFP-1', 3.0);
        $paid = $this->line(2, null, 2.0);
        $quote = $this->quote([$reward, $paid], 'rfp-1');

        $reward->expects($this->once())->method('setQty')->with(1);
        $reward->expects($this->once())->method('setCustomPrice')->with(0);
        $paid->expects($this->never())->method('setQty');
        $paid->expects($this->never())->method('setCustomPrice');
        $quote->expects($this->once())->method('setCouponCode')->with('');

        $this->freeProduct->hold($quote);
    }

    /**
     * @dataProvider refusals
     */
    public function testAddToCartRefusesWithoutTouchingTheCart(
        string $kind,
        bool $codeSpent,
        ?array $answer,
        string $productType,
        string $reason
    ): void {
        $quote = $this->quote([$this->line(1, null, 1.0, 40.0)]);
        $this->rewardCoupon->method('isSpent')->willReturn($codeSpent);
        $this->claims->method('find')->willReturn($answer);
        $this->products->method('get')->willReturn($this->product($productType));

        $quote->expects($this->never())->method('addProduct');
        $this->quotes->expects($this->never())->method('save');

        $this->assertSame(
            ['added' => false, 'reason' => $reason],
            $this->freeProduct->addToCart($quote, 7, $kind, 'rfp-1')
        );
    }

    public static function refusals(): array
    {
        $available = ['outcome' => 'available', 'sku' => 'GIFT'];

        return [
            'a code already used here' => [ClaimedFreeProducts::CODE, true, $available, 'simple', 'used'],
            'LoyaltyLion not reached' => [ClaimedFreeProducts::SESSION, false, null, 'simple', 'unreachable'],
            'LoyaltyLion says no' => [
                ClaimedFreeProducts::SESSION,
                false,
                ['outcome' => 'unavailable', 'reason' => 'expired'],
                'simple',
                'expired',
            ],
            'the rest of the cart spends too little' => [
                ClaimedFreeProducts::SESSION,
                false,
                $available + ['minimum_requirements' => ['cost' => 50]],
                'simple',
                'cart_requirement',
            ],
            'the product needs a choice made' => [
                ClaimedFreeProducts::SESSION,
                false,
                $available,
                'configurable',
                'product_needs_options',
            ],
        ];
    }

    public function testAddToCartAddsTheProductAtNoChargeAndCollectsShippingAgain(): void
    {
        $quote = $this->quote([]);
        $added = $this->line(5, null);
        $this->rewardCoupon->method('isSpent')->willReturn(false);
        $this->claims->method('find')->willReturn(['outcome' => 'available', 'sku' => 'GIFT']);
        $this->products->method('get')->willReturn($this->product('simple'));
        $quote->method('addProduct')->willReturn($added);

        $added->expects($this->once())->method('setCustomPrice')->with(0);
        $quote->getShippingAddress()->expects($this->once())->method('setCollectShippingRates')->with(true);
        $this->quotes->expects($this->once())->method('save')->with($quote);

        $this->assertSame(
            ['added' => true, 'reason' => null],
            $this->freeProduct->addToCart($quote, 7, ClaimedFreeProducts::CODE, 'rfp-1')
        );
    }

    public function testAnOrderLosesTheRewardsNoLongerAllowedAndKeepsOnesThatCouldNotBeChecked(): void
    {
        $unchecked = $this->line(1, 'session:UNCHECKED');
        $ended = $this->line(2, 'session:ENDED');
        $spent = $this->line(3, 'code:SPENT');
        $paid = $this->line(4, null, 1.0, 60.0);
        $quote = $this->quote([$unchecked, $ended, $spent, $paid]);
        $this->rewardCoupon->method('isSpent')->willReturnCallback(static fn ($code) => $code === 'SPENT');
        $this->claims->method('find')->willReturnCallback(
            static fn ($websiteId, $customerId, $kind, $id) => $id === 'UNCHECKED'
                ? null
                : ['outcome' => 'unavailable', 'reason' => 'expired']
        );

        $removed = [];
        $quote->method('removeItem')->willReturnCallback(function ($id) use (&$removed, $quote) {
            $removed[] = $id;

            return $quote;
        });
        $this->quotes->expects($this->once())->method('save');

        $this->assertTrue($this->freeProduct->removeRefusedLines($quote));
        $this->assertSame([2, 3], $removed);
    }

    public function testAnOrderWhoseRewardsAreAllStillAllowedIsLeftAlone(): void
    {
        $quote = $this->quote([$this->line(1, 'session:LIVE'), $this->line(2, null, 1.0, 60.0)]);
        $this->claims->method('find')->willReturn([
            'outcome' => 'available',
            'sku' => 'GIFT',
            'minimum_requirements' => ['cost' => 50],
        ]);

        $quote->expects($this->never())->method('removeItem');
        $this->quotes->expects($this->never())->method('save');

        $this->assertFalse($this->freeProduct->removeRefusedLines($quote));
    }

    public function testTheOrderCarriesEachRewardLinesClaimAndLabelFromTheCart(): void
    {
        $label = [['label' => 'Reward', 'value' => 'Free with your points']];
        $rewardLine = $this->line(11, 'session:AB12', 1.0, 0.0, ['additional_options' => json_encode($label)]);
        $paidLine = $this->line(12, null);
        $quote = $this->quote([$rewardLine, $paidLine]);
        $quote->method('getItemById')->willReturnMap([[11, $rewardLine], [12, $paidLine]]);

        $rewardOrderLine = $this->orderLine(11, ['info_buyRequest' => ['qty' => 1]]);
        $paidOrderLine = $this->orderLine(12, []);
        $order = $this->createMock(Order::class);
        $order->method('getAllItems')->willReturn([$rewardOrderLine, $paidOrderLine]);

        $rewardOrderLine->expects($this->once())->method('setProductOptions')->with([
            'info_buyRequest' => ['qty' => 1],
            FreeProduct::OPTION_CODE => 'session:AB12',
            'additional_options' => $label,
        ]);
        $paidOrderLine->expects($this->never())->method('setProductOptions');

        $this->freeProduct->markOrderLines($order, $quote);
    }

    public function testAnOrderRetiresTheCouponsOfItsCodeRewardsOnly(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getAllVisibleItems')->willReturn([
            $this->orderLine(1, [FreeProduct::OPTION_CODE => 'code:RFP-1']),
            $this->orderLine(2, [FreeProduct::OPTION_CODE => 'session:AB12']),
        ]);

        $this->rewardCoupon->expects($this->once())->method('retire')->with('RFP-1');

        $this->freeProduct->retireCoupons($order);
    }

    public function testAnOrderReportsOnlyTheClaimsTheModuleWroteOnItsLines(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getAllVisibleItems')->willReturn([
            $this->orderLine(1, [FreeProduct::OPTION_CODE => 'session:AB12']),
            $this->orderLine(2, [FreeProduct::OPTION_CODE => 'code:RFP-1']),
            // A paid line whose add-to-cart request named a claim: a shopper
            // controls the buy request, and nothing else.
            $this->orderLine(3, ['info_buyRequest' => ['qty' => 1, FreeProduct::OPTION_CODE => 'session:ZZ99']]),
        ]);

        $this->assertSame(['AB12'], $this->freeProduct->claimsOnOrder($order, ClaimedFreeProducts::SESSION));
        $this->assertSame(['RFP-1'], $this->freeProduct->claimsOnOrder($order, ClaimedFreeProducts::CODE));
    }

    /**
     * @return Quote&MockObject
     */
    private function quote(array $lines, ?string $couponCode = null): Quote
    {
        $store = $this->createMock(Store::class);
        $store->method('getWebsiteId')->willReturn(1);
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->addMethods(['setCollectShippingRates'])
            ->getMock();

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getAllVisibleItems',
                'getStore',
                'getStoreId',
                'removeItem',
                'getItemById',
                'getShippingAddress',
                'collectTotals',
                'addProduct',
            ])
            ->addMethods(['getCustomerId', 'getCouponCode', 'setCouponCode'])
            ->getMock();
        $quote->method('getAllVisibleItems')->willReturn($lines);
        $quote->method('getStore')->willReturn($store);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getCustomerId')->willReturn(7);
        $quote->method('getCouponCode')->willReturn($couponCode);
        $quote->method('getShippingAddress')->willReturn($address);

        return $quote;
    }

    /**
     * @param array $options the line's item options by code, besides its claim
     * @return Item&MockObject
     */
    private function line(int $id, ?string $claim, float $qty = 1.0, float $rowTotal = 0.0, array $options = []): Item
    {
        if ($claim !== null) {
            $options[FreeProduct::OPTION_CODE] = $claim;
        }

        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getOptionByCode', 'getQty', 'setQty', 'setCustomPrice', 'getBaseCalculationPrice'])
            ->addMethods(['setOriginalCustomPrice', 'getBaseRowTotal', 'getBaseDiscountAmount'])
            ->getMock();
        $item->method('getId')->willReturn($id);
        $item->method('getOptionByCode')->willReturnCallback(
            static fn ($code) => isset($options[$code]) ? new DataObject(['value' => $options[$code]]) : null
        );
        $item->method('getQty')->willReturn($qty);
        $item->method('getBaseRowTotal')->willReturn($rowTotal);
        $item->method('getBaseDiscountAmount')->willReturn(0.0);
        $item->method('getBaseCalculationPrice')->willReturn($rowTotal / $qty);

        return $item;
    }

    /**
     * @return OrderItem&MockObject
     */
    private function orderLine(int $quoteItemId, array $productOptions): OrderItem
    {
        $item = $this->createMock(OrderItem::class);
        $item->method('getQuoteItemId')->willReturn($quoteItemId);
        $item->method('getProductOptions')->willReturn($productOptions);
        $item->method('getProductOptionByCode')
            ->willReturnCallback(static fn ($code) => $productOptions[$code] ?? null);

        return $item;
    }

    /**
     * @return Product&MockObject
     */
    private function product(string $type): Product
    {
        $typeInstance = $this->createMock(AbstractType::class);
        $typeInstance->method('hasRequiredOptions')->willReturn(false);

        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn($type);
        $product->method('getTypeInstance')->willReturn($typeInstance);

        return $product;
    }
}
