<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Which theme family and checkout a website's shoppers get. LoyaltyLion's
 * storefront code runs differently on each, so it is reported with the
 * heartbeat rather than guessed at from the outside.
 */
class Storefront
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ThemeProviderInterface $themes,
        private readonly ModuleManager $modules
    ) {
    }

    /**
     * @return array{theme: string, theme_code: string, checkout: string}
     */
    public function of(WebsiteInterface $website): array
    {
        $codes = $this->themeCodes($website);
        $theme = self::family($codes);

        return [
            'theme' => $theme,
            'theme_code' => $codes[0] ?? '',
            // A Hyvä theme without Hyvä Checkout hands the checkout pages to Luma.
            'checkout' => $theme === 'hyva' && $this->modules->isEnabled('Hyva_Checkout') ? 'hyva_checkout' : 'luma',
        ];
    }

    /**
     * The family a theme belongs to, from its own code and its parents', nearest first.
     *
     * @param string[] $codes
     */
    public static function family(array $codes): string
    {
        foreach ($codes as $code) {
            if (str_starts_with($code, 'Hyva/')) {
                return 'hyva';
            }
        }
        foreach ($codes as $code) {
            if ($code === 'Magento/luma' || $code === 'Magento/blank') {
                return 'luma';
            }
        }

        return 'other';
    }

    /**
     * @return string[] the theme of the website's default store view, then each parent's
     */
    private function themeCodes(WebsiteInterface $website): array
    {
        $themeId = $this->scopeConfig->getValue(
            DesignInterface::XML_PATH_THEME_ID,
            ScopeInterface::SCOPE_STORE,
            $website->getDefaultStore()?->getId()
        );
        if (!$themeId) {
            return [];
        }

        $codes = [];
        $theme = $this->themes->getThemeById((int) $themeId);
        while ($theme && $theme->getCode() && count($codes) < 10) {
            $codes[] = (string) $theme->getCode();
            $theme = $theme->getParentTheme();
        }

        return $codes;
    }
}
