<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model\Reward;

use Loyaltylion\Core\Model\Reward\ClaimedFreeProducts;
use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use PHPUnit\Framework\TestCase;

class FreeProductTest extends TestCase
{
    public function testAnOrderReportsOnlyTheClaimsTheModuleWroteOnItsLines(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getAllVisibleItems')->willReturn([
            $this->line(['loyaltylion_reward' => 'session:AB12']),
            $this->line(['loyaltylion_reward' => 'code:RFP-1']),
            // A paid line whose add-to-cart request named a claim: a shopper
            // controls the buy request, and nothing else.
            $this->line(['info_buyRequest' => ['qty' => 1, 'loyaltylion_reward' => 'session:ZZ99']]),
        ]);

        // claimsOnOrder reads the order alone, so the collaborators stay out.
        $freeProduct = (new \ReflectionClass(FreeProduct::class))->newInstanceWithoutConstructor();

        $this->assertSame(['AB12'], $freeProduct->claimsOnOrder($order, ClaimedFreeProducts::SESSION));
        $this->assertSame(['RFP-1'], $freeProduct->claimsOnOrder($order, ClaimedFreeProducts::CODE));
    }

    private function line(array $productOptions): Item
    {
        $item = $this->createMock(Item::class);
        $item->method('getProductOptionByCode')
            ->willReturnCallback(static fn ($code) => $productOptions[$code] ?? null);

        return $item;
    }
}
