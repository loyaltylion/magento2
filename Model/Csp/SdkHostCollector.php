<?php

namespace Loyaltylion\Core\Model\Csp;

use Loyaltylion\Core\Block\Checkout\CheckoutRedemptionLayoutProcessor;
use Loyaltylion\Core\Helper\Config;
use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Lets the SDK's scripts run on pages with a strict Content Security Policy,
 * which Magento 2.4.7 and later apply to the checkout. Only while redeeming
 * points at checkout is on, the one feature that needs the SDK there.
 */
class SdkHostCollector implements PolicyCollectorInterface
{
    private $_config;

    private $_scopeConfig;

    public function __construct(Config $config, ScopeConfigInterface $scopeConfig)
    {
        $this->_config = $config;
        $this->_scopeConfig = $scopeConfig;
    }

    public function collect(array $defaultPolicies = []): array
    {
        $enabled = $this->_scopeConfig->isSetFlag(
            CheckoutRedemptionLayoutProcessor::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );

        if (!$enabled) {
            return $defaultPolicies;
        }

        $host = $this->_config->getSdkHost();

        foreach (["script-src", "connect-src", "style-src", "img-src", "font-src"] as $id) {
            $defaultPolicies[] = new FetchPolicy($id, false, [$host]);
        }

        return $defaultPolicies;
    }
}
