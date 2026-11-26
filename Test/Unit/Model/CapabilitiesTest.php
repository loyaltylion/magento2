<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model;

use Loyaltylion\Core\Model\Capabilities;
use Loyaltylion\Core\Model\Connection;
use PHPUnit\Framework\TestCase;

class CapabilitiesTest extends TestCase
{
    public function testAWebsiteOffersFreeProductsOnlyWhenItPutsThemInTheCart(): void
    {
        $capabilities = new Capabilities();

        $this->assertContains(
            Capabilities::SEAMLESS_FREE_PRODUCT,
            $capabilities->forWebsite($this->connection(true))
        );
        $this->assertNotContains(
            Capabilities::SEAMLESS_FREE_PRODUCT,
            $capabilities->forWebsite($this->connection(false))
        );
        $this->assertContains(Capabilities::SDK_CART, $capabilities->forWebsite($this->connection(false)));
    }

    private function connection(bool $addsFreeProductsToCart): Connection
    {
        return new Connection(15, 'token', 'pat_key', 'secret', 'api', 'sdk', 'static', 'platform', $addsFreeProductsToCart);
    }
}
