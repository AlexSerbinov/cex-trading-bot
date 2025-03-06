<?php
// Script for running a single bot for a specific pair
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tradingBotMain.php';

// Getting the pair from the command line arguments
if ($argc < 2) {
    echo "Usage: php bot.php PAIR_NAME\n";
    exit(1);
}

$pair = $argv[1];

// Checking if the configuration exists for the pair
try {
    $pairConfig = Config::getPairConfig($pair);
} catch (RuntimeException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Running the bot for this pair
$bot = new TradingBot($pair);
$bot->run(); 