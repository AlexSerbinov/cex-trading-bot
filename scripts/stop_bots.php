<?php
// Script for stopping bots
declare(strict_types=1);

require_once __DIR__ . '/../src/core/Logger.php';
require_once __DIR__ . '/../src/core/BotProcess.php';

$logger = Logger::getInstance();
$logger->log("Stopping all bots...");

// Create an object for process management
$botProcess = new BotProcess();

// Stop all processes
$botProcess->stopAllProcesses();

// Find the TradingBotManager process
$command = "ps aux | grep 'php src/core/TradingBotManager.php' | grep -v grep";
exec($command, $output);

if (!empty($output)) {
    // Get the process PID
    $parts = preg_split('/\s+/', trim($output[0]));
    $pid = $parts[1];
    
    // Stop the process
    $logger->log("Stopping TradingBotManager (PID: {$pid})");
    exec("kill {$pid}");
    sleep(1); // Wait for process to finish
}

$logger->log("All bots stopped"); 