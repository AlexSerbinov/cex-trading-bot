<?php

declare(strict_types=1);

require_once __DIR__ . '/BotRunner.php';
require_once __DIR__ . '/ErrorHandler.php';
require_once __DIR__ . '/Logger.php';

// Ініціалізація Logger та ErrorHandler
$environment = getenv('ENVIRONMENT') ?: 'local';
$logDir = __DIR__ . '/../../data/logs/' . $environment;

// Створюємо директорії для логів, якщо вони не існують
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/bot.log';
$errorLogFile = $logDir . '/bots_error.log';

// Ініціалізуємо логер та обробник помилок
Logger::getInstance(true, $logFile);
ErrorHandler::initialize($errorLogFile);

// Перевірка переданих аргументів
if ($argc < 2) {
    echo "Використання: php TradingBotRunner.php <пара>\n";
    echo "Приклад: php TradingBotRunner.php DOGE_BTC\n";
    exit(1);
}

// Отримання пари з аргументів
$pair = $argv[1];

// Логуємо початок роботи бота
$logger = Logger::getInstance();
$logger->log("Запуск бота для пари {$pair} (PID: " . getmypid() . ")");

try {
    // Створення та запуск бота
    $runner = new BotRunner($pair);
    $runner->run();
} catch (\Throwable $e) {
    // Обробка будь-яких помилок, які не були перехоплені раніше
    $logger->error("Критична помилка при запуску бота для пари {$pair}: " . $e->getMessage());
    $logger->error("Stack trace: " . $e->getTraceAsString());
    exit(1);
} 