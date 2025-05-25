<?php

require_once __DIR__ . '/src/core/Logger.php';
/**
 * Router for PHP built-in web server
 * 
 * This file is used as a router for php -S
 * It handles all requests that do not match actual files
 */

// Log request information
$logMessage = date('[Y-m-d H:i:s]') . " Request: " . $_SERVER['REQUEST_URI'] . " | Method: " . $_SERVER['REQUEST_METHOD'];
$environment = getenv('ENVIRONMENT') ?: 'local';
$logger = Logger::getInstance(true, __DIR__ . '/data/logs/' . $environment . '/router.log');
$logger->log($logMessage);  

// If the requested file exists (static content), serve it directly
if (preg_match('/\.(?:css|js|jpe?g|gif|png|icon)$/', $_SERVER["REQUEST_URI"])) {
    return false; // serve static content directly
}

// For all other requests, use index.php
require __DIR__ . '/index.php'; 