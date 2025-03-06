<?php

declare(strict_types=1);

// Configuration for CORS
require_once __DIR__ . '/cors.php';

// Connect the necessary files
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/ExchangeManager.php';
require_once __DIR__ . '/BotManager.php';

// Set headers for CORS and JSON
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Processing OPTIONS request (for CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the request path
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api';
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove the base path
$path = str_replace($basePath, '', $path);

// Split the path into parts
$pathParts = explode('/', trim($path, '/'));

// Check if the request is to Swagger UI
if (empty($pathParts[0]) || in_array($pathParts[0], ['swagger-ui', 'swagger', 'docs'])) {
    require_once __DIR__ . '/swagger.php';
    exit;
}

// Check if the request is to swagger.json
if ($pathParts[0] === 'swagger.json') {
    header('Content-Type: application/json');
    readfile(__DIR__ . '/../../swagger.json');
    exit;
}

// Logging the request for debugging
$logger = Logger::getInstance();
$logger->log("Index Request: " . $path . ", Parts: " . json_encode($pathParts));

// Create an instance of BotManager
$botManager = new BotManager();

// Processing requests
try {
    // Requests to /api/bots
    if ($pathParts[0] === 'bots') {
        // GET /api/bots - getting all bots
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && count($pathParts) === 1) {
            $bots = $botManager->getAllBots();
            echo json_encode($bots);
            exit;
        }
        
        // GET /api/bots/{id} - getting a bot by ID
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int)$pathParts[1];
            $bot = $botManager->getBotById($id);
            
            if (!$bot) {
                http_response_code(404);
                echo json_encode(['error' => 'Бот не знайдений']);
                exit;
            }
            
            echo json_encode($bot);
            exit;
        }
        
        // POST /api/bots - creating a new bot
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && count($pathParts) === 1) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($data === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON format']);
                exit;
            }
            
            $bot = $botManager->addBot($data);
            echo json_encode($bot);
            exit;
        }
        
        // PUT /api/bots/{id} - updating a bot
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int)$pathParts[1];
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($data === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON format']);
                exit;
            }
            
            $bot = $botManager->updateBot($id, $data);
            
            if (!$bot) {
                http_response_code(404);
                echo json_encode(['error' => 'Bot not found']);
                exit;
            }
            
            echo json_encode($bot);
            exit;
        }
        
        // DELETE /api/bots/{id} - deleting a bot
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int)$pathParts[1];
            $result = $botManager->deleteBot($id);
            
            if (!$result) {
                http_response_code(404);
                echo json_encode(['error' => 'Bot not found']);
                exit;
            }
            
            echo json_encode(['success' => true, 'message' => 'Bot deleted']);
            exit;
        }
        
        // PUT /api/bots/{id}/enable - enabling a bot
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && count($pathParts) === 3 && is_numeric($pathParts[1]) && $pathParts[2] === 'enable') {
            $id = (int)$pathParts[1];
            $bot = $botManager->enableBot($id);
            
            if (!$bot) {
                http_response_code(404);
                echo json_encode(['error' => 'Bot not found']);
                exit;
            }
            
            echo json_encode($bot);
            exit;
        }
        
        // PUT /api/bots/{id}/disable - disabling a bot
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && count($pathParts) === 3 && is_numeric($pathParts[1]) && $pathParts[2] === 'disable') {
            $id = (int)$pathParts[1];
            $bot = $botManager->disableBot($id);
            
            if (!$bot) {
                http_response_code(404);
                echo json_encode(['error' => 'Bot not found']);
                exit;
            }
            
            echo json_encode($bot);
            exit;
        }
        
        // PUT /api/bots/{id}/update-balance - updating a bot's balance
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && count($pathParts) === 3 && is_numeric($pathParts[1]) && $pathParts[2] === 'update-balance') {
            $id = (int)$pathParts[1];
            $bot = $botManager->updateBotTradeAmountMax($id);
            
            if (!$bot) {
                http_response_code(404);
                echo json_encode(['error' => 'Bot not found']);
                exit;
            }
            
            echo json_encode($bot);
            exit;
        }
    }
    
    // GET /api/logs - getting logs
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'logs') {
        $logger = new Logger(false); // Do not display in the console
        
        // Get the number of lines from the query parameter
        $lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 0;
        
        $logContent = $logger->getLogContent($lines);
        
        // Return logs in JSON format
        echo json_encode(['logs' => $logContent]);
        exit;
    }
    
    // GET /api/exchanges - getting the list of supported exchanges
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'exchanges') {
        echo json_encode(['exchanges' => Config::SUPPORTED_EXCHANGES]);
        exit;
    }
    
    // GET /api/pairs - getting the list of available pairs
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'pairs') {
        $exchangeManager = ExchangeManager::getInstance();
        $pairs = $exchangeManager->getAvailablePairsOnTradeServer();
        
        echo json_encode(['pairs' => $pairs]);
        exit;
    }
    
    // If we get here, the request does not match any endpoint
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 