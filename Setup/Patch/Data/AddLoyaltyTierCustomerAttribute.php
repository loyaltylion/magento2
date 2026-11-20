<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Adds the `loyaltylion_tier` customer attribute, which LoyaltyLion keeps set
 * to the member's tier name through the REST API. Magento has no REST call
 * that creates a customer attribute, so the module has to.
 */
class AddLoyaltyTierCustomerAttribute implements DataPatchInterface
{
    public const ATTRIBUTE_CODE = 'loyaltylion_tier';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory
    ) {
    }

    public function apply(): self
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        if ($customerSetup->getAttributeId(Customer::ENTITY, self::ATTRIBUTE_CODE)) {
            return $this;
        }

        $customerSetup->addAttribute(
            Customer::ENTITY,
            self::ATTRIBUTE_CODE,
            [
                'type' => 'varchar',
                'label' => 'LoyaltyLion tier',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'system' => false,
                'position' => 1000,
                'sort_order' => 1000,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'is_searchable_in_grid' => true,
            ]
        );

        $entityType = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = (int) $entityType->getDefaultAttributeSetId();
        $attributeGroupId = (int) $customerSetup->getDefaultAttributeGroupId(
            Customer::ENTITY,
            $attributeSetId
        );

        $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, self::ATTRIBUTE_CODE);
        $attribute->addData([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'used_in_forms' => ['adminhtml_customer'],
        ]);
        $attribute->save();

        // Adobe Commerce's customer segments read only attributes marked for
        // them, and Open Source has no such column
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('customer_eav_attribute');
        if ($connection->tableColumnExists($table, 'is_used_for_customer_segment')) {
            $connection->update(
                $table,
                ['is_used_for_customer_segment' => 1],
                ['attribute_id = ?' => (int) $attribute->getId()]
            );
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
