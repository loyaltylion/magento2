<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model\Reward;

use Loyaltylion\Core\Model\Reward\RewardCoupon;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\Data\CouponInterface;
use Magento\SalesRule\Api\Data\CouponSearchResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RewardCouponTest extends TestCase
{
    /** @var CouponRepositoryInterface&MockObject */
    private $coupons;

    /** @var LoggerInterface&MockObject */
    private $logger;

    private RewardCoupon $rewardCoupon;

    protected function setUp(): void
    {
        $this->coupons = $this->createMock(CouponRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $criteria = $this->createMock(SearchCriteriaBuilder::class);
        $criteria->method('addFilter')->willReturnSelf();
        $criteria->method('setPageSize')->willReturnSelf();
        $criteria->method('create')->willReturn($this->createMock(SearchCriteria::class));

        $this->rewardCoupon = new RewardCoupon($this->coupons, $criteria, $this->logger);
    }

    /**
     * @dataProvider coupons
     */
    public function testACodeIsSpentOnceAnOrderUsedItsCouponOrItsCouponIsGone(?int $timesUsed, bool $spent): void
    {
        $this->storeHas($timesUsed === null ? [] : [$this->coupon($timesUsed)]);

        $this->assertSame($spent, $this->rewardCoupon->isSpent('RFP-1'));
    }

    public static function coupons(): array
    {
        return [
            'removed when a free line used it' => [null, true],
            'used on an order as a coupon' => [1, true],
            'not used' => [0, false],
        ];
    }

    public function testRetiringACodeDeletesItsCoupon(): void
    {
        $this->storeHas([$this->coupon(0, 42)]);

        $this->coupons->expects($this->once())->method('deleteById')->with(42);

        $this->rewardCoupon->retire('RFP-1');
    }

    public function testACouponThatCannotBeDeletedIsLoggedAndLeftForTheOrderToGoThrough(): void
    {
        $this->storeHas([$this->coupon(0, 42)]);
        $this->coupons->method('deleteById')->willThrowException(new \RuntimeException('locked'));

        $this->logger->expects($this->once())->method('error');

        $this->rewardCoupon->retire('RFP-1');
    }

    private function storeHas(array $coupons): void
    {
        $result = $this->createMock(CouponSearchResultInterface::class);
        $result->method('getItems')->willReturn($coupons);
        $this->coupons->method('getList')->willReturn($result);
    }

    private function coupon(int $timesUsed, int $id = 1): CouponInterface
    {
        $coupon = $this->createMock(CouponInterface::class);
        $coupon->method('getTimesUsed')->willReturn($timesUsed);
        $coupon->method('getCouponId')->willReturn($id);

        return $coupon;
    }
}
