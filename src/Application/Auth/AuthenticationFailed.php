<?php

declare(strict_types=1);

namespace ClubBilling\Application\Auth;

use RuntimeException;

/** We don't know who the caller is (HTTP 401). */
final class AuthenticationFailed extends RuntimeException {}
