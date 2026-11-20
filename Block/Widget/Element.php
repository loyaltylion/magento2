<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Block\Widget;

use Loyaltylion\Core\Model\Config\Source\Element as Elements;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Widget\Block\BlockInterface;

/**
 * A LoyaltyLion SDK element a merchant places on a CMS page or block. The SDK
 * renders into it on every page it loads on.
 */
class Element extends AbstractBlock implements BlockInterface
{
    protected function _toHtml(): string
    {
        $element = Elements::ELEMENTS[(string) $this->getData('element')] ?? null;
        if ($element === null) {
            return '';
        }

        [$tag, $attribute, $value] = $element;

        return sprintf(
            '<%1$s class="loyaltylion-element" %2$s="%3$s"></%1$s>',
            $tag,
            $this->_escaper->escapeHtmlAttr($attribute),
            $this->_escaper->escapeHtmlAttr($value)
        );
    }
}
