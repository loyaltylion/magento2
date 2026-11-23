<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const PREFIX = 'loyaltylion/connection/';
    private const OLD_MODULE_PREFIX = 'loyaltylion_core/general/';
    private const FREE_PRODUCT_CART = 'loyaltylion/storefront/free_product_cart';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * @param int|string|null $websiteId null reads the website of the current request
     */
    public function forWebsite($websiteId = null): Connection
    {
        $siteId = $this->value('site_id', $websiteId);
        $sdkToken = $this->secret('sdk_token', $websiteId);
        $oldToken = $this->oldValue('token', $websiteId);
        $oldSecret = $this->oldValue('secret', $websiteId);

        return new Connection(
            $siteId === null ? null : (int) $siteId,
            $this->value('site_token', $websiteId) ?? $oldToken,
            $this->secret('api_key', $websiteId),
            $sdkToken ?? $oldSecret,
            (string) $this->value('api_host', $websiteId),
            (string) $this->value('sdk_host', $websiteId),
            (string) $this->value('sdk_static_host', $websiteId),
            (string) $this->value('platform_host', $websiteId),
            $this->scopeConfig->isSetFlag(self::FREE_PRODUCT_CART, ScopeInterface::SCOPE_WEBSITE, $websiteId),
            $oldToken !== null && $oldSecret !== null,
            $sdkToken !== null
        );
    }

    private function value(string $field, $websiteId): ?string
    {
        return $this->read(self::PREFIX . $field, $websiteId);
    }

    /**
     * What module 1.x stored for the website. The storefront keeps working on
     * it after an upgrade; sending orders needs the new settings.
     */
    private function oldValue(string $field, $websiteId): ?string
    {
        return $this->read(self::OLD_MODULE_PREFIX . $field, $websiteId);
    }

    private function read(string $path, $websiteId): ?string
    {
        $value = trim((string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE, $websiteId));

        return $value === '' ? null : $value;
    }

    private function secret(string $field, $websiteId): ?string
    {
        $stored = $this->value($field, $websiteId);

        return $stored === null ? null : $this->encryptor->decrypt($stored);
    }
}
