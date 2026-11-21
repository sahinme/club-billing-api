<?php

declare(strict_types=1);

namespace ClubBilling\Tests\Unit;

use ClubBilling\Infrastructure\Logging\RedactionProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class RedactionProcessorTest extends TestCase
{
    public function testRemovesSecretsAndMasksEmails(): void
    {
        $record = new LogRecord(new DateTimeImmutable(), 'test', Level::Info, 'message', context: [
            'password' => 'hunter2',
            'email' => 'anna@example.com',
            'payment_id' => 42,
            'request' => ['headers' => ['Authorization' => 'Bearer abc'], 'access_token' => 'abc'],
        ]);

        $context = new RedactionProcessor()($record)->context;

        self::assertSame('[REDACTED]', $context['password']);
        self::assertSame('a***@example.com', $context['email']);
        self::assertSame(42, $context['payment_id']);
        self::assertSame(['headers' => ['Authorization' => '[REDACTED]'], 'access_token' => '[REDACTED]'], $context['request']);
    }
}
