<?php

namespace Loyaltylion\Core\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists the events waiting for LoyaltyLion or parked as failed, retries failed
 * ones, and sends what is due without waiting for cron.
 */
class EventsCommand extends Command
{
    private $_outbox;
    private $_queue;

    public function __construct(
        \Loyaltylion\Core\Model\Outbox $outbox,
        \Loyaltylion\Core\Model\EventQueue\Proxy $queue
    ) {
        $this->_outbox = $outbox;
        $this->_queue = $queue;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName("loyaltylion:events")
            ->setDescription("List, retry or send the events queued for LoyaltyLion")
            ->addArgument("action", InputArgument::OPTIONAL, "list, retry or send", "list")
            ->addOption("status", null, InputOption::VALUE_REQUIRED, "list: pending, sent or failed")
            ->addOption("limit", null, InputOption::VALUE_REQUIRED, "list: how many", 50)
            ->addOption("id", null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, "retry: these failed events only");
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        switch ($input->getArgument("action")) {
            case "list":
                $rows = $this->_outbox->listByStatus($input->getOption("status"), (int) $input->getOption("limit"));
                $table = new Table($output);
                $table->setHeaders(["id", "store", "kind", "subject", "status", "attempts", "next attempt (UTC)", "last status", "last error", "created (UTC)"]);
                foreach ($rows as $row) {
                    $table->addRow([
                        $row["id"], $row["store_id"], $row["kind"], $row["subject"], $row["status"],
                        $row["attempts"], $row["next_attempt_at"], $row["last_status"],
                        mb_substr((string) $row["last_error"], 0, 80), $row["created_at"],
                    ]);
                }
                $table->render();
                return 0;

            case "retry":
                $ids = $input->getOption("id");
                $count = $this->_outbox->retryFailed(empty($ids) ? null : array_map("intval", $ids));
                $output->writeln("Queued " . $count . " failed event(s) to be sent again.");
                return 0;

            case "send":
                $output->writeln(json_encode($this->_queue->sendDue()));
                return 0;
        }

        $output->writeln("<error>Unknown action. Use list, retry or send.</error>");
        return 1;
    }
}
