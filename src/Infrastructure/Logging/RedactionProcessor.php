<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Safety net that scrubs sensitive values from every log record before it is written.
 *
 * The first rule is still "don't log secrets": no code here passes a password or token to the logger.
 * This processor catches the mistake someone will eventually make anyway.
 */
final class RedactionProcessor implements ProcessorInterface
{
    /** Any context key containing one of these is replaced completely. */
    private const array SECRET_KEYS = ['password', 'token', 'authorization', 'secret', 'cookie', 'card', 'cvc', 'iban'];

    /** Personal data we keep partly, so support can still recognise the person. */
    private const array EMAIL_KEYS = ['email'];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            $name = strtolower((string) $key);

            if (self::containsAny($name, self::SECRET_KEYS)) {
                $data[$key] = '[REDACTED]';
            } elseif (self::containsAny($name, self::EMAIL_KEYS) && is_string($value)) {
                $data[$key] = self::maskEmail($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    /** @param list<string> $needles */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** "anna@example.com" becomes "a***@example.com". */
    private static function maskEmail(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return '[REDACTED]';
        }

        return $email[0] . '***' . substr($email, $at);
    }
}
