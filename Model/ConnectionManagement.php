<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model;

use Loyaltylion\Core\Api\ConnectionManagementInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConnectionManagement implements ConnectionManagementInterface
{
    private const PREFIX = 'loyaltylion/connection/';

    public function __construct(
        private readonly WriterInterface $writer,
        private readonly EncryptorInterface $encryptor,
        private readonly StoreManagerInterface $storeManager,
        private readonly TypeListInterface $caches,
        private readonly Heartbeat $heartbeat
    ) {
    }

    public function save(int $websiteId, int $siteId, string $siteToken, string $apiKey, string $sdkToken): bool
    {
        // Throws for a website this store doesn't have, which answers 404.
        $website = $this->storeManager->getWebsite($websiteId);

        $settings = [
            'site_id' => (string) $siteId,
            'site_token' => $siteToken,
            // Stored the way the settings page stores them.
            'api_key' => $this->encryptor->encrypt($apiKey),
            'sdk_token' => $this->encryptor->encrypt($sdkToken),
        ];
        foreach ($settings as $field => $value) {
            $this->writer->save(self::PREFIX . $field, $value, ScopeInterface::SCOPE_WEBSITES, (int) $website->getId());
        }

        // Pages cached before this carry no LoyaltyLion script, or the previous site's.
        $this->caches->cleanType('config');
        $this->caches->cleanType('full_page');
        $this->storeManager->reinitStores();

        try {
            $this->heartbeat->send();
        } catch (\Throwable $e) {
            // The hourly report follows.
        }

        return true;
    }
}
