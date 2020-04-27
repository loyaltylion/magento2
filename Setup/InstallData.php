<?php
namespace Loyaltylion\Core\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Integration\Model\ConfigBasedIntegrationManager;

class InstallData implements InstallDataInterface
{
    /**
     * ConfigBasedIntegrationManager lets us
     * save our integration to the integrations list, ready
     * for activation by the merchant
     *
     * @var ConfigBasedIntegrationManager
     */

    private $_integrationManager;

    /**
     * InstallData constructor
     *
     * @param ConfigBasedIntegrationManager $integrationManager
     */

    public function __construct(
        ConfigBasedIntegrationManager $integrationManager
    ) {
        $this->_integrationManager = $integrationManager;
    }

    /**
     * {@inheritdoc}
     */

    public function install(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $this->_integrationManager->processIntegrationConfig(['LoyaltyLion']);
    }
}
