<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model;

use Loyaltylion\Core\Model\Storefront;
use PHPUnit\Framework\TestCase;

class StorefrontTest extends TestCase
{
    public function testAMerchantsOwnThemeBuiltOnHyvaIsHyva(): void
    {
        $this->assertSame('hyva', Storefront::family(['Acme/shop', 'Hyva/default', 'Hyva/reset']));
    }

    public function testAMerchantsOwnThemeBuiltOnLumaIsLuma(): void
    {
        $this->assertSame('luma', Storefront::family(['Acme/shop', 'Magento/luma', 'Magento/blank']));
    }

    public function testAThemeBuiltOnNeitherIsReportedAsOther(): void
    {
        $this->assertSame('other', Storefront::family(['Acme/headless']));
        $this->assertSame('other', Storefront::family([]));
    }
}
