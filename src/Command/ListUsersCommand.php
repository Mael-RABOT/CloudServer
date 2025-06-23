<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:list-users',
    description: 'List all users in the system',
)]
class ListUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (table, json)', 'table')
            ->setHelp('This command lists all users in the system.

Examples:
  <info>php bin/console app:list-users</info>
  <info>php bin/console app:list-users --format=json</info>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $input->getOption('format');

        $users = $this->entityManager->getRepository(User::class)->findAll();

        if (empty($users)) {
            $io->warning('No users found in the system.');
            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $userData = [];
            foreach ($users as $user) {
                $userData[] = [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ];
            }
            $io->write(json_encode($userData, JSON_PRETTY_PRINT));
        } else {
            $tableData = [];
            foreach ($users as $user) {
                $tableData[] = [
                    $user->getId(),
                    $user->getUsername(),
                    $user->getEmail(),
                    implode(', ', $user->getRoles()),
                ];
            }

            $io->table(
                ['ID', 'Username', 'Email', 'Roles'],
                $tableData
            );
        }

        return Command::SUCCESS;
    }
}