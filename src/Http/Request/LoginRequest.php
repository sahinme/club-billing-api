<?php

declare(strict_types=1);

namespace ClubBilling\Http\Request;

use Psr\Http\Message\ServerRequestInterface;

final readonly class LoginRequest
{
    private function __construct(
        public string $email,
        #[\SensitiveParameter]
        public string $password,
    ) {}

    /** @throws ValidationException */
    public static function fromHttp(ServerRequestInterface $request): self
    {
        $body = JsonBody::decode($request);
        $errors = [];

        $email = $body['email'] ?? null;
        if (!is_string($email) || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Must be a valid email address.';
            $email = null;
        }

        $password = $body['password'] ?? null;
        // Upper limit so nobody can make us hash a 10 MB "password".
        if (!is_string($password) || $password === '' || strlen($password) > 1024) {
            $errors['password'] = 'Required, at most 1024 characters.';
            $password = null;
        }

        if ($email === null || $password === null) {
            throw new ValidationException($errors);
        }

        return new self(strtolower($email), $password);
    }
}
