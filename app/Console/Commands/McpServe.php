<?php

namespace App\Console\Commands;

use App\Mcp\McpServer;
use Illuminate\Console\Command;

class McpServe extends Command
{
    protected $signature = 'mcp:serve';

    protected $description = 'Serve the Mealboard MCP server over STDIN/STDOUT (JSON-RPC 2.0, line-delimited)';

    public function handle(McpServer $server): int
    {
        $server->serve(STDIN, STDOUT);

        return self::SUCCESS;
    }
}
