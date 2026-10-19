<?php

declare(strict_types=1);

use App\Mcp\Servers\TramaServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * MCP endpoint for AI assistants (Streamable HTTP). Authenticated with an API
 * token that holds mcp:access, created by hand or issued through OAuth (see
 * routes/oauth.php); each tool then needs its own resource ability.
 */
Mcp::web('/mcp', TramaServer::class)
    ->middleware(['demo.disabled', 'auth:sanctum', 'mcp.access', 'throttle:mcp']);
