<?php

declare(strict_types=1);

// Знаходимо процес PHP-сервера
$command = "ps aux | grep 'php -S 0.0.0.0:8080' | grep -v grep";
exec($command, $output);

if (empty($output)) {
    echo "API-сервер не запущений" . PHP_EOL;
    exit;
}

// Отримуємо PID процесу
$parts = preg_split('/\s+/', trim($output[0]));
$pid = $parts[1];

// Зупиняємо процес
echo "Зупинка API-сервера (PID: {$pid})" . PHP_EOL;
exec("kill {$pid}");
echo "API-сервер зупинений" . PHP_EOL; 