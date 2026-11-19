<?php

namespace Loyaltylion\Core\Cron;

class SendEvents
{
    private $_queue;
    private $_logger;

    public function __construct(
        \Loyaltylion\Core\Model\EventQueue $queue,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_queue = $queue;
        $this->_logger = $logger;
    }

    public function execute()
    {
        $counts = $this->_queue->sendDue();
        if (array_sum($counts) > 0) {
            $this->_logger->info("[LoyaltyLion] Sent queued events: " . json_encode($counts));
        }
    }
}
