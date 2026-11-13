<?php

namespace App\Command;

use App\Service\MaintenancePlanner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:maintenance:check',
    description: 'Recalcule les échéances et crée les interventions préventives échues.'
)]
final class MaintenanceCheckCommand extends Command
{
    public function __construct(private MaintenancePlanner $planner)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->planner->check();
        $output->writeln(sprintf(
            'Planning mis à jour : %d état(s), %d intervention(s) créée(s), %d notification(s).',
            $result['updated'],
            $result['created'],
            $result['notified'],
        ));

        return Command::SUCCESS;
    }
}
