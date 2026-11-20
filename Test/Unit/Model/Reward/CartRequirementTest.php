<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model\Reward;

use Loyaltylion\Core\Model\Reward\CartRequirement;
use PHPUnit\Framework\TestCase;

class CartRequirementTest extends TestCase
{
    /**
     * @dataProvider carts
     */
    public function testTheRestOfTheCartDecidesWhetherTheFreeProductStays(
        ?array $requirement,
        float $paidCost,
        int $paidQuantity,
        bool $met
    ): void {
        $this->assertSame($met, CartRequirement::met($requirement, $paidCost, $paidQuantity));
    }

    public static function carts(): array
    {
        return [
            'no requirement, empty cart' => [null, 0.0, 0, true],
            'spend reached' => [['cost' => 50], 50.0, 1, true],
            'spend not reached' => [['cost' => 50], 49.99, 3, false],
            'a paid item present' => [['quantity' => 1], 7.0, 1, true],
            'only the free product' => [['quantity' => 1], 0.0, 0, false],
        ];
    }
}
