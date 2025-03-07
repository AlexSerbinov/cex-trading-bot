<?php

// Determine the content type based on the requested file
$requestUri = $_SERVER['REQUEST_URI'];

if (strpos($requestUri, 'swagger.json') !== false) {
    // If swagger.json is requested, return JSON
    header('Content-Type: application/json');
    readfile(__DIR__ . '/../../swagger.json');
} elseif (strpos($requestUri, 'swagger-ui') !== false || $requestUri === '/swagger-ui') {
    // If swagger-ui.html is requested, return HTML
    header('Content-Type: text/html');
    readfile(__DIR__ . '/swagger-ui.html');
} else {
    // Otherwise, redirect to swagger-ui.html
    header('Location: /swagger-ui');
} 