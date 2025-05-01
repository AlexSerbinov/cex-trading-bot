<?php

require_once __DIR__ . '/src/core/Logger.php';
/**
 * Маршрутизатор для вбудованого веб-сервера PHP
 * 
 * Цей файл використовується як router для php -S
 * Він обробляє всі запити, які не відповідають реальним файлам
 */

// Записуємо інформацію про запит у лог
$logMessage = date('[Y-m-d H:i:s]') . " Request: " . $_SERVER['REQUEST_URI'] . " | Method: " . $_SERVER['REQUEST_METHOD'];
$environment = getenv('ENVIRONMENT') ?: 'local';
$logger = Logger::getInstance(true, __DIR__ . '/data/logs/' . $environment . '/router.log');
$logger->log($logMessage);  

// Якщо запитуваний файл існує (статичний контент), віддаємо його напряму
if (preg_match('/\.(?:css|js|jpe?g|gif|png|icon)$/', $_SERVER["REQUEST_URI"])) {
    return false; // віддаємо статичний контент напряму
}

// Для всіх інших запитів використовуємо index.php
require __DIR__ . '/index.php'; 