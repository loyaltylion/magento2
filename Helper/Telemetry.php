<?php

namespace Loyaltylion\Core\Helper;

class Telemetry
{
    private $productMetadata;
    private $moduleList;
    const MODULE_NAME = 'Loyaltylion_Core';

    public function __construct(
        \Magento\Framework\App\ProductMetadata $productMetadata,
        \Magento\Framework\Module\ModuleListInterface $moduleList
    )
    {
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
    }

    public function getSystemInfo()
    {
        $version_info = Array();
        $version_info['$magento_version'] = $this->productMetadata->getVersion();
        $version_info['$module_version'] = $this->moduleList->getOne($this::MODULE_NAME)['setup_version'];
        return $version_info;
    }
}