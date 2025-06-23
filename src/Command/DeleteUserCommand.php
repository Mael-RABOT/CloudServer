<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:delete-user',
    description: 'Delete a user from the system',
)]
class DeleteUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('identifier', InputArgument::REQUIRED, 'User ID, username, or email')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force deletion without confirmation')
            ->setHelp('This command allows you to delete a user from the system.

Examples:
  <info>php bin/console app:delete-user 2</info>
  <info>php bin/console app:delete-user test</info>
  <info>php bin/console app:delete-user test@email.fr</info>
  <info>php bin/console app:delete-user 2 --force</info>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = $input->getArgument('identifier');
        $force = $input->getOption('force');

        // Try to find user by ID, username, or email
        $user = null;

        // First try as ID
        if (is_numeric($identifier)) {
            $user = $this->entityManager->getRepository(User::class)->find($identifier);
        }

        // If not found, try as username
        if (!$user) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $identifier]);
        }

        // If still not found, try as email
        if (!$user) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $identifier]);
        }

        if (!$user) {
            $io->error(sprintf('User with identifier "%s" not found.', $identifier));
            return Command::FAILURE;
        }

        // Show user details
        $io->section('User Details');
        $io->table(
            ['Property', 'Value'],
            [
                ['ID', $user->getId()],
                ['Username', $user->getUsername()],
                ['Email', $user->getEmail()],
                ['Roles', implode(', ', $user->getRoles())],
            ]
        );

        // Confirm deletion
        if (!$force) {
            $confirmation = $io->confirm(
                sprintf('Are you sure you want to delete user "%s" (ID: %d)?', $user->getUsername(), $user->getId()),
                false
            );

            if (!$confirmation) {
                $io->info('Deletion cancelled.');
                return Command::SUCCESS;
            }
        }

        // Delete user
        $username = $user->getUsername();
        $userId = $user->getId();

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $io->success(sprintf('User "%s" (ID: %d) has been deleted successfully.', $username, $userId));

        return Command::SUCCESS;
    }
}