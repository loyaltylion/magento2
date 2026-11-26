<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Reward;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\Data\CouponInterface;
use Psr\Log\LoggerInterface;

/**
 * The store's coupon for a free product code reward. LoyaltyLion creates each
 * claim's code as a coupon, for stores that don't add the product to the cart,
 * and the module can add the same code as a free line instead. A free line
 * never goes through the coupon, so Magento's own limit on the coupon's uses
 * can't stop one code being spent twice: once as a coupon, once as a line.
 */
class RewardCoupon
{
    public function __construct(
        private readonly CouponRepositoryInterface $coupons,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Whether the code can no longer be added as a free line: an order has
     * used its coupon, or a free line used it and the coupon was removed.
     *
     * @param string $code
     * @return bool
     */
    public function isSpent(string $code): bool
    {
        $coupon = $this->find($code);

        return $coupon === null || (int) $coupon->getTimesUsed() > 0;
    }

    /**
     * Removes the code's coupon, once an order has used the code as a free line.
     *
     * @param string $code
     * @return void
     */
    public function retire(string $code): void
    {
        try {
            $coupon = $this->find($code);
            if ($coupon !== null) {
                $this->coupons->deleteById((int) $coupon->getCouponId());
            }
        } catch (\Exception $e) {
            $this->logger->error(
                '[LoyaltyLion] Could not remove the coupon of a used free product: ' . $e->getMessage()
            );
        }
    }

    /**
     * The coupon with this code, if the store has one.
     *
     * @param string $code
     * @return CouponInterface|null
     */
    private function find(string $code): ?CouponInterface
    {
        $criteria = $this->searchCriteriaBuilder->addFilter('code', $code)->setPageSize(1)->create();
        $coupons = $this->coupons->getList($criteria)->getItems();

        return $coupons ? reset($coupons) : null;
    }
}
