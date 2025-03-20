<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/core/SingleProcessManager.php';
require_once __DIR__ . '/core/Logger.php';

// Налаштування обробки помилок
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $logger = Logger::getInstance();
    $logger->error("Error [$errno] $errstr in $errfile:$errline");
    return true;
});

// Налаштування обробки винятків
set_exception_handler(function (\Throwable $e) {
    $logger = Logger::getInstance();
    $logger->error("Uncaught exception: " . $e->getMessage());
    $logger->error("Exception trace: " . $e->getTraceAsString());
});

// Логування старту
$logger = Logger::getInstance();
$logger->log("---------------------------------------------");
$logger->log("Запуск асинхронного менеджера в єдиному процесі");
$logger->log("---------------------------------------------");

try {
    // Шлях до конфігурації
    $configPath = __DIR__ . '/../config/bots_config.json';
    
    // Створення менеджера
    $manager = new SingleProcessManager($configPath, 30);
    
    // Запуск менеджера
    $manager->run();
    
} catch (\Throwable $e) {
    $logger->error("Критична помилка: " . $e->getMessage());
    $logger->error($e->getTraceAsString());
    exit(1);
} 