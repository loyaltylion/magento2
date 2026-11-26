<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model\Cart;

use Loyaltylion\Core\Model\Cart\SdkCart;
use Loyaltylion\Core\Model\Config;
use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\TestCase;

class SdkCartTest extends TestCase
{
    public function testTheCartTotalsAreBeforeAndAfterDiscountsInBaseCurrency(): void
    {
        $cart = $this->sdkCart()->of($this->quote([$this->line(2, 50.0, 10.0), $this->line(1, 20.0, 0.0)], null));

        $this->assertSame(7000, $cart['original_total_price']);
        $this->assertSame(6000, $cart['total_price']);
        $this->assertSame(1000, $cart['total_discount']);
        $this->assertSame('GBP', $cart['currency']);
        $this->assertSame(2500, $cart['items'][0]['price']);
        $this->assertSame(2000, $cart['items'][0]['discounted_price']);
        $this->assertSame(5000, $cart['items'][0]['original_line_price']);
        $this->assertSame(4000, $cart['items'][0]['line_price']);
    }

    public function testTheCartCarriesItsCouponAsItsOneDiscountCode(): void
    {
        $this->assertSame(
            [['code' => 'WELCOME10', 'applicable' => true]],
            $this->sdkCart()->of($this->quote([$this->line(1, 20.0, 2.0)], 'WELCOME10'))['discount_codes']
        );
        $this->assertSame([], $this->sdkCart()->of($this->quote([$this->line(1, 20.0, 0.0)], null))['discount_codes']);
    }

    private function sdkCart(): SdkCart
    {
        return new SdkCart($this->createMock(FreeProduct::class), $this->createMock(Config::class));
    }

    private function quote(array $lines, ?string $coupon): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllVisibleItems', 'getId', 'isVirtual'])
            ->addMethods(['getCouponCode', 'getBaseCurrencyCode'])
            ->getMock();
        $quote->method('getAllVisibleItems')->willReturn($lines);
        $quote->method('getId')->willReturn(5);
        $quote->method('getCouponCode')->willReturn($coupon);
        $quote->method('getBaseCurrencyCode')->willReturn('GBP');

        return $quote;
    }

    private function line(int $qty, float $baseRowTotal, float $baseDiscount): Item
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getProductUrl'])
            ->addMethods(['getUrlKey'])
            ->getMock();

        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getQty', 'getName', 'getSku', 'getProduct', 'getProductType'])
            ->addMethods(['getProductId', 'getBaseRowTotal', 'getBaseDiscountAmount', 'getIsVirtual'])
            ->getMock();
        $item->method('getQty')->willReturn($qty);
        $item->method('getBaseRowTotal')->willReturn($baseRowTotal);
        $item->method('getBaseDiscountAmount')->willReturn($baseDiscount);
        $item->method('getProduct')->willReturn($product);

        return $item;
    }
}
