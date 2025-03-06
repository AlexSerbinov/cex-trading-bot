<?php

declare(strict_types=1);

// Setting CORS headers for access from any source
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Processing OPTIONS requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Logging the request for debugging
$logger = Logger::getInstance();
$logger->log("API Request: " . $_SERVER['REQUEST_URI']);

// Redirecting all requests to the API
require_once __DIR__ . '/api/index.php'; 