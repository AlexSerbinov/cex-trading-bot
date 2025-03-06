<?php

declare(strict_types=1);

require_once __DIR__ . '/BotManager.php';

// Set the headers for CORS and JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Process the OPTIONS request (for CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get the request path
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Remove the base path (if it exists)
if ($pathParts[0] === 'api') {
    array_shift($pathParts);
}

// Create the bot manager
$botManager = new BotManager();

try {
    // Process requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $pathParts[0] === 'bots') {
        // GET /bots - get all bots
        if (count($pathParts) === 1) {
            $bots = $botManager->getAllBots();
            echo json_encode($bots);
        }
        // GET /bots/{id} - get bot by ID
        elseif (count($pathParts) === 2 && is_numeric($pathParts[1])) {
            $id = (int) $pathParts[1];
            $bot = $botManager->getBotById($id);
            
            if ($bot === null) {
                http_response_code(404);
                echo json_encode(['error' => "Bot with ID {$id} not found"]);
            } else {
                echo json_encode($bot);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Unknown path']);
        }
    }
    // POST /bots - add new bot
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $pathParts[0] === 'bots' && count($pathParts) === 1) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON format']);
            exit;
        }
        
        $bot = $botManager->addBot($data);
        echo json_encode($bot);
    }
    // PUT /bots/{id} - update bot
    elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' && $pathParts[0] === 'bots' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
        $id = (int) $pathParts[1];
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON format']);
            exit;
        }
        
        $bot = $botManager->updateBot($id, $data);
        
        if ($bot === null) {
            http_response_code(404);
            echo json_encode(['error' => "Bot with ID {$id} not found"]);
        } else {
            echo json_encode($bot);
        }
    }
    // DELETE /bots/{id} - delete bot
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $pathParts[0] === 'bots' && count($pathParts) === 2 && is_numeric($pathParts[1])) {
        $id = (int) $pathParts[1];
        $result = $botManager->deleteBot($id);
        
        if (!$result) {
            http_response_code(404);
            echo json_encode(['error' => "Bot with ID {$id} not found"]);
        } else {
            echo json_encode(['success' => true]);
        }
    }
    // POST /bots/{id}/status - change bot status
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $pathParts[0] === 'bots' && count($pathParts) === 3 && is_numeric($pathParts[1]) && $pathParts[2] === 'status') {
        $id = (int) $pathParts[1];
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($data === null || !isset($data['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON format or missing status parameter']);
            exit;
        }
        
        $bot = $botManager->changeBotStatus($id, $data['status']);
        
        if ($bot === null) {
            http_response_code(404);
            echo json_encode(['error' => "Bot with ID {$id} not found"]);
        } else {
            echo json_encode($bot);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Unknown path or method']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
} 