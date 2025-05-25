<?php

declare(strict_types=1);

// Include necessary files
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/core/Logger.php';
require_once __DIR__ . '/src/core/ErrorHandler.php';
require_once __DIR__ . '/src/helpers/LogManager.php';

// Enable error display
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Consider environment to determine log path
$environment = getenv('ENVIRONMENT') ?: 'local';
$errorLogFile = __DIR__ . '/data/logs/' . $environment . '/bots_error.log';

// Initialize error handler with the error file path
ErrorHandler::initialize($errorLogFile);

// Initialize log manager on startup
$logManager = App\helpers\LogManager::getInstance();
$logManager->forceCheck(); // Force check on startup

// Get request path
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Set CORS headers - allow requests from any origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS requests (for CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle Swagger UI paths
if ($path === '/swagger-ui' || $path === '/swagger-ui/' || $path === '/swagger') {
    header('Content-Type: text/html');
    readfile(__DIR__ . '/public/docs/index.html');
    exit;
}

// Handle swagger.json request
if ($path === '/swagger.json') {
    header('Content-Type: application/json');
    readfile(__DIR__ . '/public/docs/swagger.json');
    exit;
}

// Handle requests for static files in public/docs directory
if (strpos($path, '/docs/') === 0) {
    $filePath = __DIR__ . '/public' . $path;
    if (file_exists($filePath)) {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'css': header('Content-Type: text/css'); break;
            case 'js': header('Content-Type: application/javascript'); break;
            case 'json': header('Content-Type: application/json'); break;
            case 'html': header('Content-Type: text/html'); break;
            default: header('Content-Type: application/octet-stream');
        }
        readfile($filePath);
        exit;
    }
}

// ===== SIMPLIFIED API REQUEST HANDLING =====

// If the request is for the API
if (strpos($path, '/api/') === 0 || $path === '/api') {
    
    // Include necessary files
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/src/core/Logger.php';
    require_once __DIR__ . '/src/core/ExchangeManager.php';
    require_once __DIR__ . '/src/api/BotManager.php';
    
    // Set JSON header for all API responses
    header('Content-Type: application/json');
    
    // Log the request
    $logger = Logger::getInstance();
    $logger->log("API Request: " . $path . " | Method: " . $_SERVER['REQUEST_METHOD']);
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $logger->log("Request body: " . $rawInput);
    }
    
    // Remove '/api' from the beginning of the path
    $apiPath = substr($path, 4); // remove '/api'
    if (empty($apiPath)) {
        $apiPath = '/';
    }
    
    // Split the path into parts
    $pathParts = explode('/', trim($apiPath, '/'));
    $logger->log("API Path parts: " . json_encode($pathParts));
    
    // Create BotManager object
    $botManager = new BotManager();
    
    try {
        // API request handling
        
        // GET /api/bots - get all bots
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && (empty($pathParts[0]) || $pathParts[0] === 'bots') && count($pathParts) === 1) {
            $bots = $botManager->getAllBots();
            echo json_encode($bots);
            exit;
        }
        
        // GET /api/bots/{id} - get bot by ID
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'bots' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int) $pathParts[1];
            $bot = $botManager->getBotById($id);
            
            if ($bot === null) {
                http_response_code(404);
                echo json_encode(['error' => "Bot with ID {$id} not found"]);
            } else {
                echo json_encode($bot);
            }
            exit;
        }
        
        // POST /api/bots - create a new bot
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pathParts[0] === 'bots' && count($pathParts) === 1) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($data === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid data']);
                exit;
            }
            
            $newBot = $botManager->createBot($data);
            echo json_encode($newBot);
            exit;
        }
        
        // PUT /api/bots/{id} - update bot
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $pathParts[0] === 'bots' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int) $pathParts[1];
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($data === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid data']);
                exit;
            }
            
            $updatedBot = $botManager->updateBot($id, $data);
            
            if ($updatedBot === null) {
                http_response_code(404);
                echo json_encode(['error' => "Bot with ID {$id} not found"]);
            } else {
                echo json_encode($updatedBot);
            }
            exit;
        }
        
        // DELETE /api/bots/{id} - delete bot
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $pathParts[0] === 'bots' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int) $pathParts[1];
            $success = $botManager->deleteBot($id);
            
            if (!$success) {
                http_response_code(404);
                echo json_encode(['error' => "Bot with ID {$id} not found"]);
            } else {
                echo json_encode(['success' => true, 'message' => "Bot with ID {$id} was deleted"]);
            }
            exit;
        }
        
        // PUT /api/bots/{id}/enable - enable bot
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $pathParts[0] === 'bots' && count($pathParts) === 3 && is_numeric($pathParts[1]) && $pathParts[2] === 'enable') {
            $id = (int) $pathParts[1];
            $bot = $botManager->enableBot($id);
            
            if ($bot === null) {
                http_response_code(404);
                echo json_encode(['error' => "Bot with ID {$id} not found"]);
            } else {
                echo json_encode($bot);
            }
            exit;
        }
        
        // PUT /api/bots/{id}/disable - disable bot
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $pathParts[0] === 'bots' && count($pathParts) === 3 && is_numeric($pathParts[1]) && $pathParts[2] === 'disable') {
            $id = (int) $pathParts[1];
            $bot = $botManager->disableBot($id);
            
            if ($bot === null) {
                http_response_code(404);
                echo json_encode(['error' => "Bot with ID {$id} not found"]);
            } else {
                echo json_encode($bot);
            }
            exit;
        }
        
        // GET /api/exchanges - get list of available exchanges
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'exchanges' && count($pathParts) === 1) {
            $exchangeManager = ExchangeManager::getInstance();
            $exchanges = $exchangeManager->getExchangesList();
            echo json_encode(['exchanges' => $exchanges]);
            exit;
        }
        
        // GET /api/pairs - get list of available pairs
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'pairs' && count($pathParts) === 1) {
            $exchangeManager = ExchangeManager::getInstance();
            $pairs = $exchangeManager->getPairsList();
            echo json_encode(['pairs' => $pairs]);
            exit;
        }
        
        // GET /api/balances - get bot balances
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'balances' && count($pathParts) === 1) {
            require_once __DIR__ . '/src/core/TradeServer.php';
            
            // Using USER_ID=5 as user identifier for bots
            $userId = 5;
            $tradeServer = TradeServer::getInstance();
            $balances = $tradeServer->getUserBalances($userId);
            
            echo json_encode($balances);
            exit;
        }
        
        // POST /api/balances/topup - top up bot balance
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pathParts[0] === 'balances' && count($pathParts) === 2 && $pathParts[1] === 'topup') {
            require_once __DIR__ . '/src/core/TradeServer.php';
            
            // Get data from request body
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['currency']) || !isset($data['amount'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Currency and amount must be specified']);
                exit;
            }
            
            $currency = $data['currency'];
            $amount = $data['amount'];
            
            // Check if the amount is valid (must be a string or a number that can be converted to a string)
            if (!is_numeric($amount) || floatval($amount) <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Amount must be a positive number']);
                exit;
            }
            
            // Convert amount to a string without additional formatting
            // Ensure amount is a string without formatting
            $amount = (string)$amount;
            
            // Using USER_ID=5 as user identifier for bots
            $userId = 5;
            $tradeServer = TradeServer::getInstance();
            
            // Unique operation identifier (as a number, not a string)
            $operationId = time(); // Using time as a unique ID
            
            try {
                $logger->log("Updating balance for user {$userId} with currency {$currency}, amount {$amount}, operationId {$operationId}");
                $result = $tradeServer->updateBalance($userId, $currency, 'deposit', $operationId, $amount);
                echo json_encode(['success' => true, 'result' => $result]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Balance update error: ' . $e->getMessage()]);
            }
            exit;
        }
        
        // If no handlers were triggered
        http_response_code(404);
        echo json_encode(['error' => 'Resource not found']);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' =>  $e->getMessage()]);
        exit;
    }
}

// Default redirect to API documentation
header('Location: /swagger-ui');
exit; 