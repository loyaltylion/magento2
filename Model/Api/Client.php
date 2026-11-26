<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Api;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Loyaltylion\Core\Model\Connection;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\PackageInfo;

/**
 * Calls the routes LoyaltyLion keeps for this module, as the website's site.
 */
class Client
{
    private const TIMEOUT_SECONDS = 10;

    /** One for every call, so a run of sends reuses its connection to LoyaltyLion. */
    private ?ClientInterface $http = null;

    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly ProductMetadataInterface $magento,
        private readonly PackageInfo $packageInfo
    ) {
    }

    /**
     * @param int $timeout seconds; keep it short for a call a shopper is waiting on
     */
    public function send(
        Connection $connection,
        string $method,
        string $path,
        ?array $body = null,
        int $timeout = self::TIMEOUT_SECONDS
    ): Response {
        $options = [
            'http_errors' => false,
            'timeout' => $timeout,
            'connect_timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $connection->apiKey,
                'Accept' => 'application/json',
                'User-Agent' => $this->userAgent(),
            ],
        ];
        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $this->http ??= $this->clientFactory->create();
            $response = $this->http->request(
                $method,
                sprintf('https://%s/v2/magento/sites/%d%s', $connection->apiHost, $connection->siteId, $path),
                $options
            );
        } catch (GuzzleException $e) {
            return new Response(0, $e->getMessage());
        }

        return new Response($response->getStatusCode(), (string) $response->getBody());
    }

    private function userAgent(): string
    {
        return sprintf(
            'loyaltylion-magento2/%s (Magento %s %s)',
            $this->packageInfo->getVersion('Loyaltylion_Core'),
            $this->magento->getVersion(),
            $this->magento->getEdition()
        );
    }
}
