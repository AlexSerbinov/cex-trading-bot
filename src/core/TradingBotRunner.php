<?php

declare(strict_types=1);

require_once __DIR__ . '/BotRunner.php';
require_once __DIR__ . '/ErrorHandler.php';
require_once __DIR__ . '/Logger.php';

// Initialization of Logger and ErrorHandler
$environment = getenv('ENVIRONMENT') ?: 'local';
$logDir = __DIR__ . '/../../data/logs/' . $environment;

// Create directories for logs if they do not exist
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/bot.log';
$errorLogFile = $logDir . '/bots_error.log';

// Initialize logger and error handler
Logger::getInstance(true, $logFile);
ErrorHandler::initialize($errorLogFile);

// Checking passed arguments
if ($argc < 2) {
    echo "Usage: php TradingBotRunner.php <pair>\n";
    echo "Example: php TradingBotRunner.php DOGE_BTC\n";
    exit(1);
}

// Getting pair from arguments
$pair = $argv[1];

// Log the start of the bot
$logger = Logger::getInstance();
$logger->log("Starting bot for pair {$pair} (PID: " . getmypid() . ")");

try {
    // Creating and starting the bot
    $runner = new BotRunner($pair);
    $runner->run();
} catch (\Throwable $e) {
    // Handling any errors that were not caught earlier
    $logger->error("Critical error when starting bot for pair {$pair}: " . $e->getMessage());
    $logger->error("Stack trace: " . $e->getTraceAsString());
    exit(1);
} 