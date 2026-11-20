<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Storefront;

use Loyaltylion\Core\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Where the loyalty program shows on the storefront beyond the SDK's own
 * launcher, as the merchant set it under Stores > Configuration > LoyaltyLion.
 * Nothing shows on a website that isn't connected, whatever its settings say.
 */
class Placements
{
    public const LOYALTY_PAGE = 'loyalty_page';
    public const MENU_LINK = 'menu_link';
    public const ACCOUNT_PAGE = 'account_page';

    private const PREFIX = 'loyaltylion/storefront/';
    private const DEFAULT_URL_KEY = 'loyalty';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Config $config
    ) {
    }

    public function isOn(string $placement): bool
    {
        return $this->config->forWebsite()->showsSdk()
            && $this->scopeConfig->isSetFlag(self::PREFIX . $placement, ScopeInterface::SCOPE_WEBSITE);
    }

    public function loyaltyPageUrlKey(): string
    {
        $key = trim(
            (string) $this->scopeConfig->getValue(self::PREFIX . 'loyalty_page_url_key', ScopeInterface::SCOPE_WEBSITE),
            '/ '
        );

        return $key === '' ? self::DEFAULT_URL_KEY : $key;
    }
}
