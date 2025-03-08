<?php

// Визначаємо тип контенту на основі запитуваного файлу
$requestUri = $_SERVER['REQUEST_URI'];

if (strpos($requestUri, 'swagger.json') !== false) {
    // Якщо запитується swagger.json, повертаємо JSON
    header('Content-Type: application/json');
    readfile(__DIR__ . '/swagger.json');
} elseif (strpos($requestUri, 'swagger-ui') !== false || $requestUri === '/swagger-ui' || $requestUri === '/swagger') {
    // Якщо запитується swagger-ui або swagger, повертаємо HTML
    header('Content-Type: text/html');
    readfile(__DIR__ . '/index.html');
} else {
    // В інших випадках, перенаправляємо на swagger-ui
    header('Location: /swagger-ui');
} 