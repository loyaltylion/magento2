<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Cron;

use Loyaltylion\Core\Model\Heartbeat;

class SendHeartbeat
{
    public function __construct(private readonly Heartbeat $heartbeat)
    {
    }

    public function execute(): void
    {
        $this->heartbeat->send();
    }
}
