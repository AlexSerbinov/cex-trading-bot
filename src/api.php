<?php

declare(strict_types=1);

// Встановлюємо заголовки CORS для доступу з будь-якого джерела
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Обробка OPTIONS запитів для CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Перенаправляємо всі запити на API
require_once __DIR__ . '/api/index.php'; 