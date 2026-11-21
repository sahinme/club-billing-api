<?php

declare(strict_types=1);

namespace ClubBilling\Application\Payment;

use RuntimeException;

/** Internal signal: another request owns this Idempotency-Key. Never reaches the client as an error. */
final class IdempotencyKeyAlreadyClaimed extends RuntimeException {}
