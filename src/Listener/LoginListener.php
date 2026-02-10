<?php

namespace App\Listener;

use App\Entity\Manager;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginListener
{
    public function __construct(
        private Connection $connection
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof Manager) {
            return;
        }

        // Execute raw SQL update to avoid transaction conflicts with remember_me token persistence
        // This runs within the same transaction, so it will be committed together
        try {
            $this->connection->executeStatement(
                'UPDATE manager SET last_login = :now, updated_at = :now WHERE id = :id',
                [
                    'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'id' => $user->getId()
                ]
            );
        } catch (\Exception $e) {
            // Log error but don't break the login flow
            error_log('Failed to update last login: ' . $e->getMessage());
        }
    }
}
