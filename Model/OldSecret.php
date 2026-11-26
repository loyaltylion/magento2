<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Module 1.x's site secret, kept for a website after an upgrade until its new
 * settings are entered, and removed then: nothing reads it once the website
 * has an SDK secret token.
 */
class OldSecret
{
    public function __construct(private readonly WriterInterface $writer)
    {
    }

    /**
     * @param int $websiteId a website whose Site ID, API key and SDK secret token are set
     * @return void
     */
    public function forget(int $websiteId): void
    {
        $this->writer->delete(Config::OLD_SECRET_PATH, ScopeInterface::SCOPE_WEBSITES, $websiteId);
    }
}
