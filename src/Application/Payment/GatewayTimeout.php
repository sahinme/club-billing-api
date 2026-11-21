<?php

declare(strict_types=1);

namespace ClubBilling\Application\Payment;

use RuntimeException;

final class GatewayTimeout extends RuntimeException {}
