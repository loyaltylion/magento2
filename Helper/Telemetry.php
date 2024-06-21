<?php

namespace Loyaltylion\Core\Helper;

class Telemetry
{
    private $_productMetadata;
    private $_moduleList;
    const MODULE_NAME = "Loyaltylion_Core";

    public function __construct(
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\Module\ModuleListInterface $moduleList
    ) {
        $this->_productMetadata = $productMetadata;
        $this->_moduleList = $moduleList;
    }

    public function getSystemInfo()
    {
        $version_info = [];
        $version_info[
            '$magento_version'
        ] = $this->_productMetadata->getVersion();
        $version_info[
            '$magento_edition'
        ] = $this->_productMetadata->getEdition();
        $version_info['$module_version'] = $this->_moduleList->getOne(
            $this::MODULE_NAME
        )["setup_version"];
        return $version_info;
    }
}
