<?php

declare(strict_types=1);

namespace ClubBilling\Application\Auth;

use RuntimeException;

/** Authenticated, but not allowed (HTTP 403). */
final class AccessDenied extends RuntimeException {}
