<?php
// Скрипт для зупинки ботів
declare(strict_types=1);

require_once __DIR__ . '/../src/core/Logger.php';
require_once __DIR__ . '/../src/core/BotProcess.php';

$logger = Logger::getInstance();
$logger->log("Зупинка всіх ботів...");

// Створюємо об'єкт для управління процесами
$botProcess = new BotProcess();

// Зупиняємо всі процеси
$botProcess->stopAllProcesses();

// Знаходимо процес TradingBotManager
$command = "ps aux | grep 'php src/core/TradingBotManager.php' | grep -v grep";
exec($command, $output);

if (!empty($output)) {
    // Отримуємо PID процесу
    $parts = preg_split('/\s+/', trim($output[0]));
    $pid = $parts[1];
    
    // Зупиняємо процес
    $logger->log("Зупинка TradingBotManager (PID: {$pid})");
    exec("kill {$pid}");
    sleep(1); // Чекаємо завершення процесу
}

$logger->log("Всі боти зупинено"); 