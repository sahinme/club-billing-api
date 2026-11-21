<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Logging;

use InvalidArgumentException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    /**
     * One JSON object per line on stderr. In a container that is the right place:
     * Docker / Kubernetes collect it and ship it to the log platform (ELK, Datadog, ...).
     */
    public static function create(string $level, RequestContext $context): LoggerInterface
    {
        $formatter = new JsonFormatter();
        $formatter->includeStacktraces(true);

        $handler = new StreamHandler('php://stderr', self::parseLevel($level));
        $handler->setFormatter($formatter);

        $logger = new Logger('club-billing', [$handler]);

        // Processors run last-pushed-first: first add request facts, then redact everything.
        $logger->pushProcessor(new RedactionProcessor());
        $logger->pushProcessor(static fn(LogRecord $record): LogRecord => $record->with(extra: [
            ...$record->extra,
            'request_id' => $context->requestId,
            'user_id' => $context->userId,
        ]));

        return $logger;
    }

    /** "info", "INFO", "Info" all work. A typo in LOG_LEVEL fails at startup instead of silently logging nothing. */
    private static function parseLevel(string $name): Level
    {
        foreach (Level::cases() as $level) {
            if (strcasecmp($level->name, $name) === 0) {
                return $level;
            }
        }

        throw new InvalidArgumentException(sprintf('Unknown LOG_LEVEL "%s".', $name));
    }
}
