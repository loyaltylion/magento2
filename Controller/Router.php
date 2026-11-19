<?php

namespace Loyaltylion\Core\Controller;

/**
 * Serves the loyalty page at the URL key the merchant sets, once they turn the
 * page on. It runs before the CMS router, so a CMS page with the same key is
 * shown only while the loyalty page is off.
 */
class Router implements \Magento\Framework\App\RouterInterface
{
    private $_actionFactory;
    private $_storefront;

    public function __construct(
        \Magento\Framework\App\ActionFactory $actionFactory,
        \Loyaltylion\Core\Helper\Storefront $storefront
    ) {
        $this->_actionFactory = $actionFactory;
        $this->_storefront = $storefront;
    }

    public function match(\Magento\Framework\App\RequestInterface $request)
    {
        // a forwarded request comes back through every router with its module set
        if ($request->getModuleName() || !$this->_storefront->isLoyaltyPageOn()) {
            return null;
        }

        $identifier = trim($request->getPathInfo(), "/");

        if ($identifier !== $this->_storefront->getLoyaltyPageUrlKey()) {
            return null;
        }

        $request
            ->setModuleName("loyaltylion")
            ->setControllerName("index")
            ->setActionName("index");
        $request->setAlias(\Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);

        return $this->_actionFactory->create(\Magento\Framework\App\Action\Forward::class);
    }
}
