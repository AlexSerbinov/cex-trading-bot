<?php

declare(strict_types=1);

// Підключаємо необхідні файли
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/core/Logger.php';
require_once __DIR__ . '/src/core/ErrorHandler.php';
require_once __DIR__ . '/src/Helpers/LogManager.php';

// Включаємо відображення помилок
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Ініціалізуємо обробник помилок
ErrorHandler::initialize();

// Ініціалізуємо менеджер логів при запуску
$logManager = App\Helpers\LogManager::getInstance();
$logManager->forceCheck(); // Примусова перевірка при запуску

// Отримуємо шлях запиту
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Встановлюємо заголовки для CORS - дозволяємо запити з будь-якого джерела
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Обробка OPTIONS запитів (для CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Обробка шляхів Swagger UI
if ($path === '/swagger-ui' || $path === '/swagger-ui/' || $path === '/swagger') {
    header('Content-Type: text/html');
    readfile(__DIR__ . '/public/docs/index.html');
    exit;
}

// Обробка запиту до swagger.json
if ($path === '/swagger.json') {
    header('Content-Type: application/json');
    readfile(__DIR__ . '/public/docs/swagger.json');
    exit;
}

// Обробка запитів до статичних файлів у директорії public/docs
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

// ===== СПРОЩЕНА ОБРОБКА API-ЗАПИТІВ =====

// Якщо запит стосується API
if (strpos($path, '/api/') === 0 || $path === '/api') {
    
    // Підключаємо необхідні файли
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/src/core/Logger.php';
    require_once __DIR__ . '/src/core/ExchangeManager.php';
    require_once __DIR__ . '/src/api/BotManager.php';
    
    // Встановлюємо заголовок JSON для всіх API-відповідей
    header('Content-Type: application/json');
    
    // Логування запиту
    $logger = Logger::getInstance();
    $logger->log("API Request: " . $path . " | Method: " . $_SERVER['REQUEST_METHOD']);
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $logger->log("Request body: " . $rawInput);
    }
    
    // Видаляємо '/api' з початку шляху
    $apiPath = substr($path, 4); // видаляємо '/api'
    if (empty($apiPath)) {
        $apiPath = '/';
    }
    
    // Розбиваємо шлях на частини
    $pathParts = explode('/', trim($apiPath, '/'));
    $logger->log("API Path parts: " . json_encode($pathParts));
    
    // Створюємо об'єкт BotManager
    $botManager = new BotManager();
    
    try {
        // Обробка API запитів
        
        // GET /api/bots - отримати всіх ботів
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && (empty($pathParts[0]) || $pathParts[0] === 'bots') && count($pathParts) === 1) {
            $bots = $botManager->getAllBots();
            echo json_encode($bots);
            exit;
        }
        
        // GET /api/bots/{id} - отримати бота за ID
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'bots' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int) $pathParts[1];
            $bot = $botManager->getBotById($id);
            
            if ($bot === null) {
                http_response_code(404);
                echo json_encode(['error' => "Бот з ID {$id} не знайдений"]);
            } else {
                echo json_encode($bot);
            }
            exit;
        }
        
        // POST /api/bots - створити нового бота
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pathParts[0] === 'bots' && count($pathParts) === 1) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($data === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Некоректні дані']);
                exit;
            }
            
            $newBot = $botManager->createBot($data);
            echo json_encode($newBot);
            exit;
        }
        
        // PUT /api/bots/{id} - оновити бота
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $pathParts[0] === 'bots' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int) $pathParts[1];
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($data === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Некоректні дані']);
                exit;
            }
            
            $updatedBot = $botManager->updateBot($id, $data);
            
            if ($updatedBot === null) {
                http_response_code(404);
                echo json_encode(['error' => "Бот з ID {$id} не знайдений"]);
            } else {
                echo json_encode($updatedBot);
            }
            exit;
        }
        
        // DELETE /api/bots/{id} - видалити бота
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $pathParts[0] === 'bots' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int) $pathParts[1];
            $success = $botManager->deleteBot($id);
            
            if (!$success) {
                http_response_code(404);
                echo json_encode(['error' => "Бот з ID {$id} не знайдений"]);
            } else {
                echo json_encode(['success' => true, 'message' => "Бот з ID {$id} був видалений"]);
            }
            exit;
        }
        
        // PUT /api/bots/{id}/enable - активувати бота
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $pathParts[0] === 'bots' && count($pathParts) === 3 && is_numeric($pathParts[1]) && $pathParts[2] === 'enable') {
            $id = (int) $pathParts[1];
            $bot = $botManager->enableBot($id);
            
            if ($bot === null) {
                http_response_code(404);
                echo json_encode(['error' => "Бот з ID {$id} не знайдений"]);
            } else {
                echo json_encode($bot);
            }
            exit;
        }
        
        // PUT /api/bots/{id}/disable - деактивувати бота
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $pathParts[0] === 'bots' && count($pathParts) === 3 && is_numeric($pathParts[1]) && $pathParts[2] === 'disable') {
            $id = (int) $pathParts[1];
            $bot = $botManager->disableBot($id);
            
            if ($bot === null) {
                http_response_code(404);
                echo json_encode(['error' => "Бот з ID {$id} не знайдений"]);
            } else {
                echo json_encode($bot);
            }
            exit;
        }
        
        // GET /api/exchanges - отримати список доступних бірж
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'exchanges' && count($pathParts) === 1) {
            $exchangeManager = ExchangeManager::getInstance();
            $exchanges = $exchangeManager->getExchangesList();
            echo json_encode(['exchanges' => $exchanges]);
            exit;
        }
        
        // GET /api/pairs - отримати список доступних пар
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'pairs' && count($pathParts) === 1) {
            $exchangeManager = ExchangeManager::getInstance();
            $pairs = $exchangeManager->getPairsList();
            echo json_encode(['pairs' => $pairs]);
            exit;
        }
        
        // Якщо жоден з обробників не спрацював
        http_response_code(404);
        echo json_encode(['error' => 'Ресурс не знайдено']);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' =>  $e->getMessage()]);
        exit;
    }
}

// За замовчуванням перенаправляємо на документацію API
header('Location: /swagger-ui');
exit; 