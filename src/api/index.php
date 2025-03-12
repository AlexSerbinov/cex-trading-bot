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

// Функція для відправлення JSON-відповіді
function sendJsonResponse($data, int $statusCode = 200): void 
{
    // Очищаємо будь-який вихідний буфер, щоб уникнути додаткових даних у відповіді
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Встановлюємо заголовки
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    // Кодуємо дані в JSON і відправляємо
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Функція для відправлення помилки API
function sendApiError(string $message, int $statusCode = 400): void 
{
    sendJsonResponse(['error' => $message], $statusCode);
}

// Processing requests
try {
    // Requests to /api/bots
    if ($pathParts[0] === 'bots') {
        // GET /api/bots - getting all bots
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && count($pathParts) === 1) {
            $bots = $botManager->getAllBots();
            sendJsonResponse($bots);
        }
        
        // GET /api/bots/{id} - getting a bot by ID
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int)$pathParts[1];
            $bot = $botManager->getBotById($id);
            
            if (!$bot) {
                sendApiError('Бот не знайдений', 404);
            } else {
                sendJsonResponse($bot);
            }
        }
        
        // POST /api/bots - creating a new bot
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && count($pathParts) === 1) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($data === null) {
                sendApiError('Invalid JSON format');
            }
            
            // Перетворення плоскої структури в структуру з вкладеним масивом settings
            if (!isset($data['settings']) && isset($data['trade_amount_min'])) {
                $data['settings'] = [
                    'trade_amount_min' => $data['trade_amount_min'],
                    'trade_amount_max' => $data['trade_amount_max'],
                    'frequency_from' => $data['frequency_from'],
                    'frequency_to' => $data['frequency_to'],
                    'price_factor' => $data['price_factor'],
                    'market_gap' => $data['market_gap'],
                    'min_orders' => $data['min_orders'],
                    'max_orders' => $data['max_orders'],
                    'market_maker_order_probability' => $data['market_maker_order_probability'],
                ];
            }
            
            $bot = $botManager->addBot($data);
            sendJsonResponse($bot);
        }
        
        // PUT /api/bots/{id} - updating a bot
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int)$pathParts[1];
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($data === null) {
                sendApiError('Invalid JSON format');
            }
            
            $bot = $botManager->updateBot($id, $data);
            
            if (!$bot) {
                sendApiError('Bot not found', 404);
            } else {
                sendJsonResponse($bot);
            }
        }
        
        // DELETE /api/bots/{id} - deleting a bot
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int)$pathParts[1];
            $result = $botManager->deleteBot($id);
            
            if (!$result) {
                sendApiError("Bot not found");
            } else {
                sendJsonResponse([
                    'success' => true,
                    'message' => "Bot with ID {$id} deleted successfully"
                ]);
            }
        }
        
        // PUT /api/bots/{id}/enable - enabling a bot
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && count($pathParts) === 3 && is_numeric($pathParts[1]) && $pathParts[2] === 'enable') {
            $id = (int)$pathParts[1];
            $bot = $botManager->enableBot($id);
            
            if (!$bot) {
                sendApiError('Bot not found');
            } else {
                sendJsonResponse($bot);
            }
        }
        
        // PUT /api/bots/{id}/disable - disabling a bot
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && count($pathParts) === 3 && is_numeric($pathParts[1]) && $pathParts[2] === 'disable') {
            $id = (int)$pathParts[1];
            $bot = $botManager->disableBot($id);
            
            if (!$bot) {
                sendApiError('Bot not found');
            } else {
                sendJsonResponse($bot);
            }
        }
        
        // PUT /api/bots/{id}/update-balance - updating a bot's balance
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && count($pathParts) === 3 && is_numeric($pathParts[1]) && $pathParts[2] === 'update-balance') {
            $id = (int)$pathParts[1];
            $bot = $botManager->updateBotTradeAmountMax($id);
            
            if (!$bot) {
                sendApiError('Bot not found');
            } else {
                sendJsonResponse($bot);
            }
        }
    }
    
    // GET /api/logs - getting logs
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'logs') {
        $logger = new Logger(false); // Do not display in the console
        
        // Get the number of lines from the query parameter
        $lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 0;
        
        $logContent = $logger->getLogContent($lines);
        
        // Return logs in JSON format
        sendJsonResponse(['logs' => $logContent]);
    }
    
    // GET /api/exchanges - getting the list of supported exchanges
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'exchanges') {
        sendJsonResponse(['exchanges' => Config::SUPPORTED_EXCHANGES]);
    }
    
    // GET /api/pairs - getting the list of available pairs
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'pairs') {
        $exchangeManager = ExchangeManager::getInstance();
        $pairs = $exchangeManager->getAvailablePairsOnTradeServer();
        
        sendJsonResponse(['pairs' => $pairs]);
    }
    
    // GET /api/config - отримання конфігурації
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && count($pathParts) === 1 && $pathParts[0] === 'config') {
        $config = [
            'tradeServerUrl' => Config::getTradeServerUrl()
        ];
        
        sendJsonResponse($config);
    }
    
    // Якщо жоден обробник не спрацював, повертаємо 404
    sendApiError("Endpoint not found", 404);
    
} catch (Exception $e) {
    // Логуємо помилку
    $logger->error("API Error: " . $e->getMessage());
    
    // Відправляємо клієнту повідомлення про помилку
    sendApiError($e->getMessage(), 500);
} 