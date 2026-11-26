<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;

/**
 * Removes what the module keeps in the store when `bin/magento module:uninstall`
 * removes its data: the outbox, the settings of this version and of 1.x, its
 * scheduled jobs and the LoyaltyLion integration with its access. The
 * `loyaltylion_tier` customer attribute goes when Magento reverts the patch
 * that added it.
 */
class Uninstall implements UninstallInterface
{
    /** The name in etc/integration.xml */
    private const INTEGRATION = 'LoyaltyLion';

    public function __construct(
        private readonly IntegrationServiceInterface $integrations,
        private readonly OauthServiceInterface $oauth
    ) {
    }

    /**
     * @inheritdoc
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        $setup->startSetup();
        $connection = $setup->getConnection();

        $connection->dropTable($setup->getTable('loyaltylion_outbox'));
        foreach (['loyaltylion/%', 'loyaltylion_core/%', 'system/cron/loyaltylion/%'] as $path) {
            $connection->delete($setup->getTable('core_config_data'), ['path LIKE ?' => $path]);
        }
        $connection->delete($setup->getTable('cron_schedule'), ['job_code LIKE ?' => 'loyaltylion\_%']);

        $this->removeIntegration();

        $setup->endSetup();
    }

    /**
     * Deletes the integration as Magento's admin does: the integration, then
     * the OAuth consumer that holds its tokens.
     */
    private function removeIntegration(): void
    {
        $integration = $this->integrations->findByName(self::INTEGRATION);
        if (!$integration->getId()) {
            return;
        }

        $removed = $this->integrations->delete((int) $integration->getId());
        if (!empty($removed['consumer_id'])) {
            $this->oauth->deleteConsumer((int) $removed['consumer_id']);
        }
    }
}
