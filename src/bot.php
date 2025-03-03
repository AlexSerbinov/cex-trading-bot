<?php
// Скрипт для запуску одного бота для конкретної пари
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tradingBotMain.php';

// Отримуємо пару з аргументів командного рядка
if ($argc < 2) {
    echo "Використання: php bot.php PAIR_NAME\n";
    exit(1);
}

$pair = $argv[1];

// Перевіряємо, чи існує конфігурація для пари
try {
    $pairConfig = Config::getPairConfig($pair);
} catch (RuntimeException $e) {
    echo "Помилка: " . $e->getMessage() . "\n";
    exit(1);
}

// Запускаємо бота для цієї пари
$bot = new TradingBot($pair);
$bot->run(); 