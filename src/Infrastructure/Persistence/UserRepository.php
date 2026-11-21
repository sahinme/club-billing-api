<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Persistence;

use ClubBilling\Application\Auth\UserCredentials;
use ClubBilling\Domain\User\Role;
use ClubBilling\Domain\User\User;
use PDO;

final readonly class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findCredentialsByEmail(string $email): ?UserCredentials
    {
        $statement = $this->pdo->prepare('SELECT id, club_id, role, password_hash FROM users WHERE email = :email');
        $statement->execute(['email' => $email]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return new UserCredentials(
            user: new User((int) $row['id'], (int) $row['club_id'], Role::from((string) $row['role'])),
            passwordHash: (string) $row['password_hash'],
        );
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
            ->execute(['hash' => $passwordHash, 'id' => $userId]);
    }
}
