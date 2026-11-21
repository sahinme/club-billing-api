<?php

declare(strict_types=1);

namespace ClubBilling\Tests\Integration;

use ClubBilling\Database\Seed\DemoDataSeeder;

final class AuthenticationTest extends ApiTestCase
{
    public function testLoginReturnsABearerTokenAndStoresOnlyItsHash(): void
    {
        $response = $this->request('POST', '/v1/auth/login', ['email' => DemoDataSeeder::FRONT_DESK, 'password' => DemoDataSeeder::PASSWORD]);

        self::assertSame(200, $response->getStatusCode());
        $token = (string) self::json($response)['access_token'];
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);

        // The database holds the SHA-256 of the token, never the token itself.
        self::assertSame(0, $this->countRows('access_tokens', "token_hash = '{$token}'"));
        self::assertSame(1, $this->countRows('access_tokens', "token_hash = '" . hash('sha256', $token) . "'"));
    }

    public function testWrongPasswordAndUnknownEmailGetTheSameAnswer(): void
    {
        $wrongPassword = $this->request('POST', '/v1/auth/login', ['email' => DemoDataSeeder::FRONT_DESK, 'password' => 'wrong']);
        $unknownEmail = $this->request('POST', '/v1/auth/login', ['email' => 'nobody@amsterdam.test', 'password' => 'wrong']);

        self::assertSame(401, $wrongPassword->getStatusCode());
        self::assertSame(401, $unknownEmail->getStatusCode());
        self::assertSame(self::json($wrongPassword)['detail'], self::json($unknownEmail)['detail']);
    }

    public function testProtectedRouteRequiresAToken(): void
    {
        $response = $this->request('GET', '/v1/invoices/' . DemoDataSeeder::INVOICE_ANNA_OPEN);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function testUnknownTokenIsRejected(): void
    {
        $response = $this->request('GET', '/v1/invoices/1', headers: ['Authorization' => 'Bearer ' . str_repeat('a', 64)]);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testExpiredTokenIsRejected(): void
    {
        $token = $this->loginAs(DemoDataSeeder::FRONT_DESK);
        $this->pdo->exec('UPDATE access_tokens SET expires_at = CURRENT_TIMESTAMP(6) - INTERVAL 1 SECOND');

        $response = $this->request('GET', '/v1/invoices/1', headers: ['Authorization' => "Bearer {$token}"]);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testHealthIsPublic(): void
    {
        self::assertSame(200, $this->request('GET', '/health')->getStatusCode());
    }

    public function testLoginValidatesInput(): void
    {
        $response = $this->request('POST', '/v1/auth/login', ['email' => 'not-an-email']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['email', 'password'], array_keys((array) self::json($response)['errors']));
    }
}
