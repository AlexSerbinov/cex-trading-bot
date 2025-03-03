<?php

declare(strict_types=1);

require_once __DIR__ . '/BotManager.php';

// Встановлюємо заголовки для CORS та JSON
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Обробка OPTIONS запиту (для CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Отримуємо шлях запиту
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api';
$path = parse_url($requestUri, PHP_URL_PATH);

// Видаляємо базовий шлях
$path = str_replace($basePath, '', $path);

// Розбиваємо шлях на частини
$pathParts = explode('/', trim($path, '/'));

// Створюємо екземпляр BotManager
$botManager = new BotManager();

// Обробка запитів
try {
    // Запити до /api/bots
    if ($pathParts[0] === 'bots') {
        // GET /api/bots - отримання списку ботів
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && count($pathParts) === 1) {
            $bots = $botManager->getAllBots();
            echo json_encode($bots);
            exit;
        }
        
        // POST /api/bots - створення нового бота
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && count($pathParts) === 1) {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                http_response_code(400);
                echo json_encode(['error' => 'Некоректні дані запиту']);
                exit;
            }
            
            $bot = $botManager->addBot($data);
            echo json_encode($bot);
            exit;
        }
        
        // GET /api/bots/{id} - отримання інформації про бота
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int) $pathParts[1];
            $bot = $botManager->getBotById($id);
            
            if (!$bot) {
                http_response_code(404);
                echo json_encode(['error' => 'Бот не знайдений']);
                exit;
            }
            
            echo json_encode($bot);
            exit;
        }
        
        // PUT /api/bots/{id} - оновлення бота
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int) $pathParts[1];
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['error' => 'Некоректні дані запиту']);
                exit;
            }
            
            $bot = $botManager->updateBot($id, $data);
            
            if (!$bot) {
                http_response_code(404);
                echo json_encode(['error' => 'Бот не знайдений']);
                exit;
            }
            
            echo json_encode($bot);
            exit;
        }
        
        // DELETE /api/bots/{id} - видалення бота
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int) $pathParts[1];
            $result = $botManager->deleteBot($id);
            
            if (!$result) {
                http_response_code(404);
                echo json_encode(['error' => 'Бот не знайдений']);
                exit;
            }
            
            echo json_encode(['id' => $id, 'status' => 'deleted']);
            exit;
        }
        
        // PATCH /api/bots/{id}/disable - вимкнення бота
        if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && count($pathParts) === 3 && is_numeric($pathParts[1]) && $pathParts[2] === 'disable') {
            $id = (int) $pathParts[1];
            $bot = $botManager->disableBot($id);
            
            if (!$bot) {
                http_response_code(404);
                echo json_encode(['error' => 'Бот не знайдений']);
                exit;
            }
            
            echo json_encode(['id' => $id, 'status' => 'disabled']);
            exit;
        }
    }
    
    // GET /api/logs - отримання логів
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'logs') {
        $logger = new Logger(false); // Не виводимо в консоль
        
        // Отримуємо кількість рядків з параметра запиту
        $lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 0;
        
        $logContent = $logger->getLogContent($lines);
        
        // Повертаємо логи у форматі JSON
        echo json_encode(['logs' => $logContent]);
        exit;
    }
    
    // Якщо дійшли сюди, значить запит не відповідає жодному ендпоінту
    http_response_code(404);
    echo json_encode(['error' => 'Ендпоінт не знайдений']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 