<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a user for public/JWT login (http://localhost:8090)',
)]
final class CreateUserCommand extends \Symfony\Component\Console\Command\Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email (login)')
            ->addArgument('password', InputArgument::REQUIRED, 'Password')
            ->addArgument('phone', InputArgument::OPTIONAL, 'Phone number', '+37000000000')
            ->addArgument('firstName', InputArgument::OPTIONAL, 'First name', 'Admin')
            ->addArgument('lastName', InputArgument::OPTIONAL, 'Last name', 'User');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $phone = $input->getArgument('phone');
        $firstName = $input->getArgument('firstName');
        $lastName = $input->getArgument('lastName');

        if ($this->userRepository->findOneBy(['email' => $email])) {
            $io->warning(sprintf('User with email "%s" already exists.', $email));
            return self::SUCCESS;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPlainPassword($password);
        $user->setPhone($phone);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles(['ROLE_USER']);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('User "%s" created. You can log in at http://localhost:8090', $email));
        return self::SUCCESS;
    }
}
