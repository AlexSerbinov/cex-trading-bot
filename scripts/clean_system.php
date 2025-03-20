<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/core/Logger.php';

$logger = Logger::getInstance();
$logger->log("Starting system cleanup...");

// 1. Clean all PID-files
$pidDir = __DIR__ . '/../data/pids';
if (is_dir($pidDir)) {
    $logger->log("Cleaning PID-files...");
    $files = glob($pidDir . '/*.pid');
    foreach ($files as $file) {
        $logger->log("Deleting file: " . basename($file));
        unlink($file);
    }
}

// 2. Stop all bot processes
$logger->log("Stopping all bot processes...");
$command = "ps aux | grep BotRunner | grep -v grep | awk '{print $2}' | xargs -r kill -9";
exec($command);

// 3. Check the configuration file
$configFile = __DIR__ . '/../config/bots_config.json';
if (file_exists($configFile)) {
    $logger->log("Checking the configuration file...");
    $content = file_get_contents($configFile);
    $config = json_decode($content, true);
    
    if ($config === null) {
        $logger->log("ERROR: Configuration file contains invalid JSON!");
    } else {
        $logger->log("Configuration file is valid. Found pairs: " . implode(", ", array_keys($config)));
        
        // Update the modification time of the file to ensure it is reloaded
        touch($configFile);
    }
}

$logger->log("System cleanup completed."); 