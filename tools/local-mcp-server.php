<?php

declare(strict_types=1);

/**
 * NeNe Vault — Local MCP server entry point.
 *
 * Reads JSON-RPC 2.0 messages from STDIN and writes responses to STDOUT.
 * Tool catalog: docs/mcp/tools.json
 * OpenAPI contract: docs/openapi/openapi.yaml
 *
 * Environment variables:
 *   NENE2_LOCAL_API_BASE_URL   API base URL (default: http://localhost:8600)
 *   NENE2_LOCAL_JWT_SECRET     JWT secret — required for authenticated tools
 *
 * Usage (Claude Desktop claude_desktop_config.json):
 * {
 *   "mcpServers": {
 *     "nene-vault": {
 *       "command": "php",
 *       "args": ["/path/to/nene-vault/tools/local-mcp-server.php"],
 *       "env": {
 *         "NENE2_LOCAL_API_BASE_URL": "http://localhost:8600",
 *         "NENE2_LOCAL_JWT_SECRET": "your-vault-jwt-secret"
 *       }
 *     }
 *   }
 * }
 */

use Nene2\Auth\LocalBearerTokenVerifier;
use Nene2\Mcp\LocalMcpException;
use Nene2\Mcp\LocalMcpServer;
use Nene2\Mcp\LocalMcpToolCatalog;
use Nene2\Mcp\NativeLocalMcpHttpClient;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$apiBaseUrl = (string) (getenv('NENE2_LOCAL_API_BASE_URL') ?: 'http://localhost:8600');

$bearerToken = null;
$jwtSecret = getenv('NENE2_LOCAL_JWT_SECRET');

if (is_string($jwtSecret) && $jwtSecret !== '') {
    $v = new LocalBearerTokenVerifier($jwtSecret);
    $bearerToken = $v->issue([
        'sub'  => 'mcp-server',
        'role' => 'admin',
        'iat'  => time(),
        'exp'  => time() + 86400,
    ]);
}

$server = new LocalMcpServer(
    new LocalMcpToolCatalog($root . '/docs/mcp/tools.json'),
    new NativeLocalMcpHttpClient($bearerToken),
    $apiBaseUrl,
);

while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);

    if ($line === '') {
        continue;
    }

    try {
        $message = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($message)) {
            throw new LocalMcpException('JSON-RPC message must be an object.');
        }

        $response = $server->handle($message);

        if ($response === null) {
            continue;
        }
    } catch (Throwable $exception) {
        $response = [
            'jsonrpc' => '2.0',
            'id'      => null,
            'error'   => [
                'code'    => -32700,
                'message' => $exception->getMessage(),
            ],
        ];
    }

    fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
}
