<?php

declare(strict_types=1);

namespace Lubie\Db;

use RuntimeException;

/** Raised when the database cannot be reached after retries (cold start, outage). */
final class DatabaseUnavailable extends RuntimeException
{
}
