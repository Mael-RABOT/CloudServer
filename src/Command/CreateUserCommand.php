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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a new user account',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The username')
            ->addArgument('email', InputArgument::REQUIRED, 'The email address')
            ->addArgument('password', InputArgument::REQUIRED, 'The password')
            ->addOption('admin', 'a', InputOption::VALUE_NONE, 'Create user with admin role')
            ->addOption('roles', 'r', InputOption::VALUE_REQUIRED, 'Comma-separated list of roles', 'ROLE_USER')
            ->setHelp('This command allows you to create a user account from the command line.

Examples:
  <info>php bin/console app:create-user john john@example.com password123</info>
  <info>php bin/console app:create-user admin admin@example.com password123 --admin</info>
  <info>php bin/console app:create-user user user@example.com password123 --roles="ROLE_USER,ROLE_MODERATOR"</info>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy([
            'username' => $username
        ]);

        if ($existingUser) {
            $io->error(sprintf('User with username "%s" already exists.', $username));
            return Command::FAILURE;
        }

        $existingEmail = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $email
        ]);

        if ($existingEmail) {
            $io->error(sprintf('User with email "%s" already exists.', $email));
            return Command::FAILURE;
        }

        // Create new user
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        // Set roles
        if ($input->getOption('admin')) {
            $user->setRoles(['ROLE_ADMIN']);
        } else {
            $roles = array_map('trim', explode(',', $input->getOption('roles')));
            $user->setRoles($roles);
        }

        // Validate the user entity
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $io->error('Validation failed:');
            foreach ($errors as $error) {
                $io->error(sprintf('- %s: %s', $error->getPropertyPath(), $error->getMessage()));
            }
            return Command::FAILURE;
        }

        // Persist user
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('User "%s" created successfully with roles: %s', $username, implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}