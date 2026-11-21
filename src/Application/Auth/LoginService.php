<?php

declare(strict_types=1);

namespace ClubBilling\Application\Auth;

use ClubBilling\Infrastructure\Persistence\UserRepository;
use Psr\Log\LoggerInterface;

final readonly class LoginService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private TokenService $tokens,
        private LoggerInterface $logger,
    ) {}

    /** @throws AuthenticationFailed */
    public function login(string $email, #[\SensitiveParameter] string $password): IssuedToken
    {
        $credentials = $this->users->findCredentialsByEmail($email);

        if (!$this->passwords->verify($password, $credentials?->passwordHash) || $credentials === null) {
            // Same message for "no such user" and "wrong password": don't tell an attacker which one it was.
            // The email is masked by RedactionProcessor before it is written.
            $this->logger->warning('auth.login_failed', ['email' => $email]);
            throw new AuthenticationFailed('Invalid email or password.');
        }

        // Transparently upgrade old hashes while we have the plain password in hand.
        if ($this->passwords->needsRehash($credentials->passwordHash)) {
            $this->users->updatePasswordHash($credentials->user->id, $this->passwords->hash($password));
        }

        $token = $this->tokens->issueFor($credentials->user);
        $this->logger->info('auth.login_succeeded', ['user_id' => $credentials->user->id]);

        return $token;
    }
}
