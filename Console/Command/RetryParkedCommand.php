<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Console\Command;

use Loyaltylion\Core\Model\Outbox\Outbox;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Puts the orders and customers LoyaltyLion refused back in the queue. A
 * refused row is otherwise sent again only when its order or customer changes,
 * which does not happen when the cause was the store's settings.
 */
class RetryParkedCommand extends Command
{
    private const WEBSITE = 'website';

    public function __construct(private readonly Outbox $outbox)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('loyaltylion:outbox:retry-parked')
            ->setDescription('Queue again the orders and customers LoyaltyLion refused')
            ->addOption(self::WEBSITE, null, InputOption::VALUE_REQUIRED, 'Only this website id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $website = $input->getOption(self::WEBSITE);
        $queued = $this->outbox->retryParked($website === null ? null : (int) $website);

        $output->writeln(json_encode(['queued_again' => $queued] + $this->outbox->summary()));

        return Command::SUCCESS;
    }
}
