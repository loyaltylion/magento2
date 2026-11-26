<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Setup\Patch\Data;

use Loyaltylion\Core\Model\Config;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * Module 1.x stored each website's site secret in plain text. This encrypts
 * it where a website still signs shoppers with it, and removes it where the
 * website already has its new connection settings, which replace it.
 */
class EncryptOldSecret implements DataPatchInterface, PatchRevertableInterface
{
    /** The settings that, all set, replace the old secret */
    private const NEW_SETTINGS = [
        'loyaltylion/connection/site_id',
        'loyaltylion/connection/api_key',
        'loyaltylion/connection/sdk_token',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['config_id', 'scope', 'scope_id', 'value'])
                ->where('path = ?', Config::OLD_SECRET_PATH)
        );

        foreach ($rows as $row) {
            if ($this->hasNewSettings($row['scope'], (int) $row['scope_id'])) {
                $connection->delete($table, ['config_id = ?' => (int) $row['config_id']]);
            } elseif (trim((string) $row['value']) !== '') {
                $connection->update(
                    $table,
                    ['value' => $this->encryptor->encrypt(trim((string) $row['value']))],
                    ['config_id = ?' => (int) $row['config_id']]
                );
            }
        }

        return $this;
    }

    /**
     * Removes the old secret. A patch is reverted only when the module is
     * uninstalled, and the secret goes with it.
     *
     * @inheritdoc
     */
    public function revert(): void
    {
        $this->moduleDataSetup->getConnection()->delete(
            $this->moduleDataSetup->getTable('core_config_data'),
            ['path = ?' => Config::OLD_SECRET_PATH]
        );
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    private function hasNewSettings(string $scope, int $scopeId): bool
    {
        $connection = $this->moduleDataSetup->getConnection();
        $set = $connection->fetchOne(
            $connection->select()
                ->from($this->moduleDataSetup->getTable('core_config_data'), ['COUNT(*)'])
                ->where('path IN (?)', self::NEW_SETTINGS)
                ->where('scope = ?', $scope)
                ->where('scope_id = ?', $scopeId)
                ->where("TRIM(COALESCE(value, '')) <> ''")
        );

        return (int) $set === count(self::NEW_SETTINGS);
    }
}
