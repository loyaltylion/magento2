<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Test\Unit\Model\Reward;

use Loyaltylion\Core\Model\Api\Client;
use Loyaltylion\Core\Model\Api\Response;
use Loyaltylion\Core\Model\Config;
use Loyaltylion\Core\Model\Connection;
use Loyaltylion\Core\Model\Reward\ClaimedFreeProducts;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ClaimedFreeProductsTest extends TestCase
{
    /**
     * @dataProvider answers
     */
    public function testOnlyAFailureAnotherTryCouldGetPastCountsAsUnreachable(int $status, ?string $outcome): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('sendsChanges')->willReturn(true);
        $config = $this->createMock(Config::class);
        $config->method('forWebsite')->willReturn($connection);
        $client = $this->createMock(Client::class);
        $client->method('send')->willReturn(new Response($status, '{"message":"no"}'));

        $claims = new ClaimedFreeProducts($config, $client, $this->createMock(LoggerInterface::class));
        $answer = $claims->find(1, 7, ClaimedFreeProducts::SESSION, 'AB12');

        $this->assertSame($outcome, $answer['outcome'] ?? null);
    }

    public static function answers(): array
    {
        return [
            'not reached' => [0, null],
            'our error' => [503, null],
            'refused key' => [401, 'unavailable'],
            'key without the scope' => [403, 'unavailable'],
            'refused request' => [422, 'unavailable'],
        ];
    }
}
