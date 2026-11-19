<?php

namespace Loyaltylion\Core\Block\Widget;

/**
 * A LoyaltyLion SDK element a merchant places on a CMS page or block. The SDK
 * renders into it on every page it loads on.
 */
class Element extends \Magento\Framework\View\Element\AbstractBlock implements
    \Magento\Widget\Block\BlockInterface
{
    protected function _toHtml()
    {
        $elements = \Loyaltylion\Core\Model\Config\Source\Element::ELEMENTS;
        $key = (string) $this->getData("element");

        if (!isset($elements[$key])) {
            return "";
        }

        [$tag, $attribute, $value] = $elements[$key];

        return sprintf(
            '<%1$s class="loyaltylion-element" %2$s="%3$s"></%1$s>',
            $tag,
            $this->_escaper->escapeHtmlAttr($attribute),
            $this->_escaper->escapeHtmlAttr($value)
        );
    }
}
