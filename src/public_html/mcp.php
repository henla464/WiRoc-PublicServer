<?php
/**
 * Streamable-HTTP MCP endpoint (stateless, tools only) for read-only WiRoc monitoring.
 * Separate from slim.php: no session, no AuthorizationMap. Authenticated either with the shared
 * mcp_api_key (sees all competitions) or with a per-user token from mcptokens.html (that user's access).
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

require __DIR__ . '/../vendor/autoload.php';

const MCP_SUPPORTED_VERSIONS = ['2024-11-05', '2025-03-26', '2025-06-18', '2025-11-25'];

function mcpRespond($status, $body = null) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    if ($body !== null) echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function mcpError($id, $code, $message) {
    return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
}

function mcpAuthHeader() {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) return $_SERVER['HTTP_AUTHORIZATION'];
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) return $value;
        }
    }
    return '';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    mcpRespond(405, mcpError(null, -32600, 'Only POST is supported'));
}

$configFile = __DIR__ . '/../config/config.ini';
$ini = file_exists($configFile) ? @parse_ini_file($configFile, true) : false;
$mcpKey = is_array($ini) ? (string)($ini['api']['mcp_api_key'] ?? '') : '';
if ($mcpKey === '' || !is_array($ini)) {
    error_log('mcp.php: mcp_api_key missing in config.ini');
    mcpRespond(500, mcpError(null, -32603, 'Server not configured'));
}

$providedKey = '';
$authHeader = mcpAuthHeader();
if (preg_match('/^\s*Bearer\s+(.+)$/i', $authHeader, $matches)) {
    $providedKey = trim($matches[1]);
}
if ($providedKey === '') {
    $providedKey = trim((string)($_SERVER['HTTP_X_AUTHORIZATION'] ?? ''));
}

$helper = null;
$helperFactory = function () use (&$helper, $ini) {
    if ($helper === null) {
        $db = $ini['database'];
        $pdo = new PDO(
            'mysql:host=' . $db['database_hostname'] . ';dbname=' . $db['database_name'] . ';charset=utf8mb4',
            $db['database_username'],
            $db['database_password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $container = new \DI\Container();
        $container->set('db', $pdo);
        $helper = new Helper($container);
    }
    return $helper;
};

// $principal: null = the shared mcp_api_key (all competitions); otherwise a user token's person.
$principal = null;
if ($providedKey === '') {
    header('WWW-Authenticate: Bearer realm="wiroc-mcp"');
    mcpRespond(401, mcpError(null, -32001, 'Unauthorized'));
}
if (!hash_equals($mcpKey, $providedKey)) {
    // user tokens are minted as wiroc_<48 hex>; skip the DB for anything else
    if (strpos($providedKey, 'wiroc_') !== 0) {
        header('WWW-Authenticate: Bearer realm="wiroc-mcp"');
        mcpRespond(401, mcpError(null, -32001, 'Unauthorized'));
    }
    try {
        $h = $helperFactory();
        $rows = $h->QueryAll(
            "SELECT t.id tokenId, u.id userId, u.isAdmin isAdmin FROM McpTokens t JOIN Users u ON u.id = t.UserId WHERE t.tokenHash = :tokenHash",
            ['tokenHash' => hash('sha256', $providedKey)]
        );
        if (!$rows) {
            header('WWW-Authenticate: Bearer realm="wiroc-mcp"');
            mcpRespond(401, mcpError(null, -32001, 'Unauthorized'));
        }
        $principal = ['userId' => (int)$rows[0]['userId'], 'isAdmin' => !empty($rows[0]['isAdmin'])];
        try {
            $h->RunSql("UPDATE McpTokens SET lastUsedTime = NOW() WHERE id = :id", ['id' => (int)$rows[0]['tokenId']]);
        } catch (Throwable $e) {
            // lastUsedTime is informational; never fail the request for it
        }
    } catch (PDOException $e) {
        error_log('mcp.php: token lookup failed: ' . $e->getMessage());
        mcpRespond(503, mcpError(null, -32603, 'Database unavailable, try again later'));
    }
}

$raw = file_get_contents('php://input');
$msg = json_decode($raw);
if (!is_object($msg)) {
    mcpRespond(200, mcpError(null, is_array($msg) ? -32600 : -32700, is_array($msg) ? 'Batch requests are not supported' : 'Parse error'));
}

$id = $msg->id ?? null;
$method = isset($msg->method) && is_string($msg->method) ? $msg->method : '';
$params = isset($msg->params) && is_object($msg->params) ? $msg->params : new stdClass();

if (!property_exists($msg, 'id')) {
    // Notification (e.g. notifications/initialized): nothing to answer.
    mcpRespond(202, null);
}

switch ($method) {
    case 'initialize':
        $requested = isset($params->protocolVersion) && is_string($params->protocolVersion) ? $params->protocolVersion : '';
        $protocol = in_array($requested, MCP_SUPPORTED_VERSIONS, true) ? $requested : '2025-06-18';
        mcpRespond(200, [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => $protocol,
                'capabilities' => ['tools' => (object)[]],
                'serverInfo' => ['name' => 'wiroc-monitor', 'version' => '1.0.0'],
                'instructions' => 'Read-only monitoring of WiRoc orienteering devices and LoRa mesh. '
                    . 'Start with get_monitoring_summary for an overview, list_devices for per-device status, '
                    . 'and get_mesh for mesh topology and root health.',
            ],
        ]);

    case 'ping':
        mcpRespond(200, ['jsonrpc' => '2.0', 'id' => $id, 'result' => (object)[]]);

    case 'tools/list':
        mcpRespond(200, ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => McpMonitorTools::definitions()]]);

    case 'tools/call':
        $toolName = isset($params->name) && is_string($params->name) ? $params->name : '';
        $known = array_column(McpMonitorTools::definitions(), 'name');
        if (!in_array($toolName, $known, true)) {
            mcpRespond(200, mcpError($id, -32602, 'Unknown tool: ' . $toolName));
        }
        $arguments = isset($params->arguments) && is_object($params->arguments) ? (array)$params->arguments : [];
        $tools = new McpMonitorTools($helperFactory, $principal);
        try {
            $text = $tools->call($toolName, $arguments);
            mcpRespond(200, ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                'content' => [['type' => 'text', 'text' => $text]],
                'isError' => false,
            ]]);
        } catch (PDOException $e) {
            error_log('mcp.php: database error in ' . $toolName . ': ' . $e->getMessage());
            mcpRespond(200, ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                'content' => [['type' => 'text', 'text' => 'Database unavailable, try again later.']],
                'isError' => true,
            ]]);
        } catch (InvalidArgumentException $e) {
            mcpRespond(200, ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                'content' => [['type' => 'text', 'text' => $e->getMessage()]],
                'isError' => true,
            ]]);
        } catch (Throwable $e) {
            error_log('mcp.php: ' . $toolName . ' failed: ' . $e->getMessage());
            mcpRespond(200, ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
                'content' => [['type' => 'text', 'text' => 'Internal error while running the tool.']],
                'isError' => true,
            ]]);
        }
        break;

    default:
        mcpRespond(200, mcpError($id, -32601, 'Method not found: ' . $method));
}
