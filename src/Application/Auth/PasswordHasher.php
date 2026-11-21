<?php

declare(strict_types=1);

namespace ClubBilling\Application\Auth;

/**
 * The one place that knows how passwords are hashed.
 *
 * Argon2id is the current OWASP recommendation: slow and memory-hard, so a stolen hash
 * is expensive to brute-force. password_hash() generates a random salt and stores the algorithm
 * and cost parameters inside the hash string, so old hashes keep working when we raise the cost.
 */
final class PasswordHasher
{
    private const string ALGORITHM = PASSWORD_ARGON2ID;

    /**
     * Hash of a random string nobody knows. Used when the email does not exist, so that a failed
     * login takes the same time either way and an attacker cannot find out which emails are registered.
     */
    private const string DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$bVJYcDVKcENvMU5VSFp0WQ$uUYJy6jk4y2QIjDHR/LGWrudP2eIocvGDmc3u+S3oq8';

    public function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, self::ALGORITHM);
    }

    /**
     * @param string|null $hash null when the user does not exist
     */
    public function verify(#[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $hash): bool
    {
        // password_verify() compares in constant time. We always call it, also for unknown users (see DUMMY_HASH).
        $matches = password_verify($password, $hash ?? self::DUMMY_HASH);

        return $hash !== null && $matches;
    }

    /** True when the hash was made with an older algorithm or cost and should be upgraded. */
    public function needsRehash(#[\SensitiveParameter] string $hash): bool
    {
        return password_needs_rehash($hash, self::ALGORITHM);
    }
}
