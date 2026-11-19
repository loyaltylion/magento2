<?php

namespace Loyaltylion\Core\Model\Config\Source;

class PageElement implements \Magento\Framework\Data\OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ["value" => "integrated-page", "label" => __("Loyalty page")],
            ["value" => "account", "label" => __("Loyalty panel")],
        ];
    }
}
