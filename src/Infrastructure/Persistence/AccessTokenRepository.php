<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Persistence;

use ClubBilling\Domain\User\Role;
use ClubBilling\Domain\User\User;
use DateTimeImmutable;
use PDO;

final readonly class AccessTokenRepository
{
    public function __construct(private PDO $pdo) {}

    public function store(int $userId, string $tokenHash, DateTimeImmutable $expiresAt): void
    {
        $this->pdo->prepare(
            'INSERT INTO access_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)',
        )->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /** The user behind a token, only if the token is not expired and not revoked. */
    public function findActiveUser(string $tokenHash): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.club_id, u.role
               FROM access_tokens t
               JOIN users u ON u.id = t.user_id
              WHERE t.token_hash = :token_hash
                AND t.revoked_at IS NULL
                AND t.expires_at > CURRENT_TIMESTAMP(6)',
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return new User((int) $row['id'], (int) $row['club_id'], Role::from((string) $row['role']));
    }
}
