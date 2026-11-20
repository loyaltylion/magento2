<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Console\Command;

use Loyaltylion\Core\Model\Capabilities;
use Loyaltylion\Core\Model\Heartbeat;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports in to LoyaltyLion now, as the cron job does each hour. LoyaltyLion
 * offers a store only what its module last said it can do, so this is how to
 * bring that up to date straight after an install or upgrade.
 */
class ReportCommand extends Command
{
    public function __construct(
        private readonly Heartbeat $heartbeat,
        private readonly Capabilities $capabilities
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('loyaltylion:report')
            ->setDescription('Tell LoyaltyLion now which version this store runs and what it can do');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(json_encode([
            'capabilities' => $this->capabilities->all(),
            'answer_by_website' => $this->heartbeat->send(),
        ]));

        return Command::SUCCESS;
    }
}
