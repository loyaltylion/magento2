<?php

namespace Loyaltylion\Core\Test\Unit\Helper;

use Loyaltylion\Core\Helper\OrderTools;

class OrderToolsTest extends \PHPUnit\Framework\TestCase
{
    private function discountCodes($coupon, array $rewardCodes)
    {
        $freeProduct = $this->getMockBuilder(
            "Loyaltylion\Core\Model\Reward\FreeProduct"
        )
            ->disableOriginalConstructor()
            ->getMock();
        $freeProduct->method("codesOnOrder")->willReturn($rewardCodes);

        $order = $this->getMockBuilder("Magento\Sales\Model\Order")
            ->disableOriginalConstructor()
            ->getMock();
        $order->method("getCouponCode")->willReturn($coupon);
        $order->method("getDiscountAmount")->willReturn(-5);

        $tools = new OrderTools(
            $this->createMock("Loyaltylion\Core\Helper\Telemetry"),
            $this->createMock("Magento\Framework\HTTP\Header"),
            $this->createMock("Loyaltylion\Core\Helper\Tracking"),
            $freeProduct
        );

        return $tools->getDiscountCodes($order)["discount_codes"];
    }

    public function testACouponAloneKeepsTheShapeReleasedModulesSend()
    {
        $this->assertSame(
            ["code" => "SAVE5", "amount" => 5],
            $this->discountCodes("SAVE5", [])
        );
    }

    public function testAFreeProductIsReportedBesideTheCouponWithNoAmount()
    {
        $this->assertSame(
            [
                ["code" => "SAVE5", "amount" => 5],
                ["code" => "RFP-1", "amount" => 0],
            ],
            $this->discountCodes("SAVE5", ["RFP-1"])
        );
    }

    public function testAFreeProductIsReportedWithoutACoupon()
    {
        $this->assertSame(
            [["code" => "RFP-1", "amount" => 0]],
            $this->discountCodes(null, ["RFP-1"])
        );
    }
}
