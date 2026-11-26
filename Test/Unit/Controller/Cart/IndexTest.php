<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Controller\Cart;

use Loyaltylion\Core\Controller\Cart\Index;
use Loyaltylion\Core\Model\Cart\SdkCart;
use Loyaltylion\Core\Model\Config;
use Loyaltylion\Core\Model\Connection;
use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    /**
     * @dataProvider websites
     */
    public function testTheSdkReadsTheCartWhereverItIsShownWhetherOrNotFreeProductsAreOn(
        ?string $siteToken,
        int $status
    ): void {
        $freeProduct = $this->createMock(FreeProduct::class);
        $freeProduct->method('isOn')->willReturn(false);
        $config = $this->createMock(Config::class);
        $config->method('forWebsite')->willReturn(
            new Connection(15, $siteToken, 'pat_key', 'secret', 'api', 'sdk', 'static', 'platform')
        );
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllVisibleItems', 'getId', 'isVirtual'])
            ->addMethods(['getBaseCurrencyCode'])
            ->getMock();
        $quote->method('getAllVisibleItems')->willReturn([]);
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($quote);

        $result = $this->createMock(Json::class);
        $result->method('setHeader')->willReturnSelf();
        $result->method('setData')->willReturnSelf();
        $result->expects($this->once())->method('setHttpResponseCode')->with($status)->willReturnSelf();
        $results = $this->createMock(JsonFactory::class);
        $results->method('create')->willReturn($result);

        $controller = new Index(
            $this->createMock(RequestInterface::class),
            $results,
            $session,
            $freeProduct,
            new SdkCart($freeProduct, $config)
        );

        $this->assertSame($result, $controller->execute());
    }

    public static function websites(): array
    {
        return [
            'shows the SDK' => ['token', 200],
            'does not show the SDK' => [null, 404],
        ];
    }
}
