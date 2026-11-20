<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Cron;

use Loyaltylion\Core\Model\Outbox\Sender;

class SendOutbox
{
    public function __construct(private readonly Sender $sender)
    {
    }

    public function execute(): void
    {
        $this->sender->send();
    }
}
