<?php

declare(strict_types=1);

namespace ClubBilling\Http\Controller;

use ClubBilling\Application\Auth\LoginService;
use ClubBilling\Http\Request\LoginRequest;
use ClubBilling\Http\Response\JsonResponder;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** POST /v1/auth/login (public) */
final readonly class LoginController
{
    public function __construct(
        private LoginService $login,
        private JsonResponder $responder,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $input = LoginRequest::fromHttp($request);
        $token = $this->login->login($input->email, $input->password);

        return $this->responder->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainToken,
            'expires_at' => $token->expiresAt->format(DateTimeInterface::ATOM),
        ]);
    }
}
