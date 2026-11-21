<?php

declare(strict_types=1);

namespace ClubBilling\Application\Auth;

use ClubBilling\Domain\User\User;
use ClubBilling\Infrastructure\Config\Config;
use ClubBilling\Infrastructure\Persistence\AccessTokenRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Opaque bearer tokens stored server-side (not JWT).
 * Why: a token can be revoked immediately (set revoked_at), and there is no signing key to manage.
 * The price is one indexed DB lookup per request, which is fine at this scale.
 */
final readonly class TokenService
{
    public function __construct(
        private AccessTokenRepository $tokens,
        private Config $config,
    ) {}

    public function issueFor(User $user): IssuedToken
    {
        // 32 bytes from the operating system's secure random generator = 256 bits. Impossible to guess.
        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = new DateTimeImmutable(sprintf('+%d seconds', $this->config->accessTokenTtlSeconds), new DateTimeZone('UTC'));

        // Only the hash is stored. The client sees the plain token once, in the login response.
        $this->tokens->store($user->id, self::hash($plainToken), $expiresAt);

        return new IssuedToken($plainToken, $expiresAt);
    }

    public function userForToken(#[\SensitiveParameter] string $plainToken): ?User
    {
        return $this->tokens->findActiveUser(self::hash($plainToken));
    }

    /**
     * SHA-256, not password_hash(): the token is long and random, so it cannot be brute-forced,
     * and a deterministic hash lets us find it with a plain index lookup.
     */
    private static function hash(#[\SensitiveParameter] string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
