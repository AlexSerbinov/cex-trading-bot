<?php

declare(strict_types=1);

require_once __DIR__ . '/BotRunner.php';

// Creating and starting the bot process manager
$runner = new BotRunner('manager');
$runner->runAsManager(); 