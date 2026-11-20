<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Controller;

use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Url;

/**
 * Serves the loyalty page at the URL key the merchant set. It runs after the
 * CMS router, so a page of the merchant's own with the same key is the one
 * shoppers get.
 */
class Router implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly Placements $placements
    ) {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        // A forwarded request comes back through every router with its module set.
        if ($request->getModuleName() || !$this->placements->isOn(Placements::LOYALTY_PAGE)) {
            return null;
        }

        $identifier = trim((string) $request->getPathInfo(), '/');
        if ($identifier !== $this->placements->loyaltyPageUrlKey()) {
            return null;
        }

        $request->setModuleName('loyaltylion')->setControllerName('page')->setActionName('index');
        $request->setAlias(Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);

        return $this->actionFactory->create(Forward::class);
    }
}
