<?php

declare(strict_types=1);

namespace App\Services\TenantTransfer;

use RuntimeException;

/**
 * Raised for any archive that can't be imported as-is. The message is shown
 * to the user verbatim, so it must be readable Italian.
 */
class InvalidTenantArchiveException extends RuntimeException {}
