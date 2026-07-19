<?php

namespace App\Mcp;

use RuntimeException;

/**
 * A JSON-RPC-reportable failure: the server answers with an error response
 * carrying this code instead of crashing the stdio loop.
 */
class McpError extends RuntimeException
{
    public function __construct(public readonly int $rpcCode, string $message)
    {
        parent::__construct($message);
    }
}
