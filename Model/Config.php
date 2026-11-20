<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const PREFIX = 'loyaltylion/connection/';
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

        return new Connection(
            $siteId === null ? null : (int) $siteId,
            $this->value('site_token', $websiteId),
            $this->secret('api_key', $websiteId),
            $this->secret('sdk_token', $websiteId),
            (string) $this->value('api_host', $websiteId),
            (string) $this->value('sdk_host', $websiteId),
            $this->scopeConfig->isSetFlag(self::FREE_PRODUCT_CART, ScopeInterface::SCOPE_WEBSITE, $websiteId)
        );
    }

    private function value(string $field, $websiteId): ?string
    {
        $value = trim((string) $this->scopeConfig->getValue(
            self::PREFIX . $field,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        ));

        return $value === '' ? null : $value;
    }

    private function secret(string $field, $websiteId): ?string
    {
        $stored = $this->value($field, $websiteId);

        return $stored === null ? null : $this->encryptor->decrypt($stored);
    }
}
