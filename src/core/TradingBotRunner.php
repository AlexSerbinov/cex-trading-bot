<?php

declare(strict_types=1);

// Ensure Composer's autoloader is included first
require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/BotRunner.php';
require_once __DIR__ . '/ErrorHandler.php';
require_once __DIR__ . '/Logger.php';

// Add use statements for ReactPHP
use React\EventLoop\Loop;
use function React\Async\async;

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

// Wrap the main execution logic in async() and start the loop
async(function () use ($pair, $logger) {
    try {
        // Створення та запуск бота
        $runner = new BotRunner($pair);
        // The run() method itself contains the main loop and await calls
        $runner->run(); 
    } catch (\Throwable $e) {
        // Обробка будь-яких помилок, які не були перехоплені раніше всередині run()
        $logger->error("Критична помилка під час роботи бота для пари {$pair}: " . $e->getMessage());
        $logger->error("Stack trace: " . $e->getTraceAsString());
        // Consider if Loop::stop() is needed here or if exit(1) is sufficient
        // Loop::stop(); 
        exit(1);
    }
})()->catch(function (\Throwable $e) use ($logger, $pair) {
    // Обробка помилок, які могли виникнути при самому запуску async функції
    $logger->error("Критична помилка при асинхронному запуску бота для пари {$pair}: " . $e->getMessage());
    $logger->error("Stack trace: " . $e->getTraceAsString());
    exit(1); // Вихід з помилкою
});

// Start the ReactPHP event loop
Loop::run();

$logger->log("Цикл подій ReactPHP зупинено для пари {$pair}"); 