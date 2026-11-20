<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Console\Command;

use Loyaltylion\Core\Model\Outbox\Outbox;
use Loyaltylion\Core\Model\Outbox\Sender;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sends the outbox now, as the cron job does each minute, and says what is left.
 */
class SendOutboxCommand extends Command
{
    public function __construct(
        private readonly Sender $sender,
        private readonly Outbox $outbox,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('loyaltylion:outbox:send')
            ->setDescription('Send waiting orders and customers to LoyaltyLion now');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Loading a customer needs an area, which a console command does not
        // have; this is the one the cron job runs in.
        $sent = $this->appState->emulateAreaCode(Area::AREA_CRONTAB, fn () => $this->sender->send());

        $output->writeln(json_encode(['sent_now' => $sent] + $this->outbox->summary()));

        return Command::SUCCESS;
    }
}
