<?php

declare(strict_types=1);

require_once __DIR__ . '/BotRunner.php';

// Перевірка переданих аргументів
if ($argc < 2) {
    echo "Використання: php TradingBotRunner.php <пара>\n";
    echo "Приклад: php TradingBotRunner.php DOGE_BTC\n";
    exit(1);
}

// Отримання пари з аргументів
$pair = $argv[1];

// Створення та запуск бота
$runner = new BotRunner($pair);
$runner->run(); 