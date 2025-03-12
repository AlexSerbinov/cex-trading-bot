<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../core/Logger.php';

// Створюємо тестовий файл для прямого запису
$testLogFile = __DIR__ . '/../../data/logs/test_logging.log';
file_put_contents($testLogFile, date('Y-m-d H:i:s') . " - Початок тесту логування\n", FILE_APPEND);

// Інформація про середовище
$phpSapi = php_sapi_name();
$serverInfo = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Not a web request';
$info = "PHP SAPI: {$phpSapi}, SERVER: {$serverInfo}";
file_put_contents($testLogFile, date('Y-m-d H:i:s') . " - {$info}\n", FILE_APPEND);

// Тестуємо Logger
$logger = Logger::getInstance();
$logger->log("Тест логування через Logger->log()");
$logger->info("Тест логування через Logger->info()");
$logger->warning("Тест логування через Logger->warning()");
$logger->error("Тест логування через Logger->error()");

// Тестуємо прямий вивід
echo "Echo: Тест прямого виводу\n";
print("Print: Тест прямого виводу\n");

// Тестуємо error_log
error_log("Error_log: Тест виводу через error_log");

// Тестуємо вивід через PHP_EOL
echo "Тест виводу через PHP_EOL" . PHP_EOL;

// Додаємо запис про завершення тесту
file_put_contents($testLogFile, date('Y-m-d H:i:s') . " - Тест логування завершено\n", FILE_APPEND);

// Якщо це веб-запит, повертаємо JSON
if (isset($_SERVER['REQUEST_METHOD'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Тест логування виконано',
        'environment' => [
            'php_sapi' => $phpSapi,
            'server_info' => $serverInfo,
            'log_file' => $testLogFile,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
} 