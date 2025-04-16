<?php

declare(strict_types=1);

require_once __DIR__ . '/BotRunner.php';

// Створення та запуск менеджера процесів ботів
$runner = new BotRunner('manager');
$runner->runAsManager(); 