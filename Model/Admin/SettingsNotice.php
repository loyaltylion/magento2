<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Admin;

use Loyaltylion\Core\Model\Config;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Notification\MessageInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Tells an admin who may change the LoyaltyLion settings, after an upgrade
 * from module 1.x, which websites still need their new connection settings.
 */
class SettingsNotice implements MessageInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    public function getIdentity()
    {
        return 'loyaltylion_new_settings_needed';
    }

    public function isDisplayed()
    {
        return $this->authorization->isAllowed('Loyaltylion_Core::config')
            && $this->websitesNeedingSettings() !== [];
    }

    public function getText()
    {
        return __(
            'LoyaltyLion needs new connection settings for %1. Orders and customers are kept and will be sent once '
            . 'you enter the Site ID, API key and SDK secret token under Stores > Configuration > LoyaltyLion.',
            implode(', ', $this->websitesNeedingSettings())
        );
    }

    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }

    /**
     * @return string[] website names
     */
    private function websitesNeedingSettings(): array
    {
        $names = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            if ($this->config->forWebsite($website->getId())->needsNewSettings()) {
                $names[] = (string) $website->getName();
            }
        }

        return $names;
    }
}
