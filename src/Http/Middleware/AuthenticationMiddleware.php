<?php

declare(strict_types=1);

namespace ClubBilling\Http\Middleware;

use ClubBilling\Application\Auth\AuthenticationFailed;
use ClubBilling\Application\Auth\TokenService;
use ClubBilling\Domain\User\User;
use ClubBilling\Infrastructure\Logging\RequestContext;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;

/**
 * Works out who is calling, on every request. Deny by default: every route needs a valid
 * bearer token unless its name is in PUBLIC_ROUTES.
 */
final readonly class AuthenticationMiddleware implements MiddlewareInterface
{
    /** Route names (config/routes.php) that can be called without a token. Keep this list short. */
    private const array PUBLIC_ROUTES = ['health', 'auth.login'];

    private const string USER_ATTRIBUTE = 'authenticated_user';

    public function __construct(
        private TokenService $tokens,
        private RequestContext $context,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeName = RouteContext::fromRequest($request)->getRoute()?->getName();
        if (in_array($routeName, self::PUBLIC_ROUTES, true)) {
            return $handler->handle($request);
        }

        $token = self::bearerToken($request) ?? throw new AuthenticationFailed('Missing bearer token.');
        $user = $this->tokens->userForToken($token) ?? throw new AuthenticationFailed('Invalid or expired token.');

        $this->context->authenticatedAs($user->id);

        return $handler->handle($request->withAttribute(self::USER_ATTRIBUTE, $user));
    }

    /** For controllers: the user this middleware attached to the request. */
    public static function userFrom(ServerRequestInterface $request): User
    {
        $user = $request->getAttribute(self::USER_ATTRIBUTE);
        if (!$user instanceof User) {
            throw new LogicException('No authenticated user on this request. Is the route public by mistake?');
        }

        return $user;
    }

    private static function bearerToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');

        // Our tokens are exactly 64 hex characters. Anything else is rejected before touching the database.
        return preg_match('/^Bearer ([a-f0-9]{64})$/', $header, $matches) === 1 ? $matches[1] : null;
    }
}
