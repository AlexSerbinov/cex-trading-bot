<?php
// Script to start a single bot for a specific pair
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/core/TradingBot.php';

// Get the pair from the command line arguments
if ($argc < 2) {
    echo "Usage: php start_bot.php PAIR_NAME\n";
    exit(1);
}

$pair = $argv[1];

// Check if the configuration exists for the pair
try {
    $pairConfig = Config::getPairConfig($pair);
} catch (RuntimeException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Start the bot for this pair
$bot = new TradingBot($pair);
$bot->run(); 