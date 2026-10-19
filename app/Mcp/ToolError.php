<?php

declare(strict_types=1);

namespace App\Mcp;

use RuntimeException;

/**
 * A failure the model should read verbatim (forbidden, not found, wrong
 * tenant…). Unlike arbitrary exceptions, its message is never hidden.
 */
class ToolError extends RuntimeException {}
