<?php
// Скрипт для зупинки ботів
require_once __DIR__ . '/Logger.php';

$logger = new Logger();

// Завантажуємо PIDs запущених ботів
if (!file_exists(__DIR__ . '/running_bots.json')) {
    $logger->log('Немає запущених ботів');
    exit(0);
}

$processes = json_decode(file_get_contents(__DIR__ . '/running_bots.json'), true);

foreach ($processes as $pair => $pid) {
    // Зупиняємо процес
    exec("kill $pid");
    $logger->log(sprintf('Зупинено бота для пари %s (PID: %s)', $pair, $pid));
}

// Видаляємо файл з PIDs
unlink(__DIR__ . '/running_bots.json');
$logger->log('Всі боти зупинено'); 