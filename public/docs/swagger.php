<?php

// Determine content type based on the requested file
$requestUri = $_SERVER['REQUEST_URI'];

if (strpos($requestUri, 'swagger.json') !== false) {
    // If swagger.json is requested, return JSON
    header('Content-Type: application/json');
    readfile(__DIR__ . '/swagger.json');
} elseif (strpos($requestUri, 'swagger-ui') !== false || $requestUri === '/swagger-ui' || $requestUri === '/swagger') {
    // If swagger-ui or swagger is requested, return HTML
    header('Content-Type: text/html');
    readfile(__DIR__ . '/index.html');
} else {
    // In other cases, redirect to swagger-ui
    header('Location: /swagger-ui');
} 