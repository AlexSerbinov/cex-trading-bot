<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/TradingBot.php';
require_once __DIR__ . '/Logger.php';

/**
 * Script for running a separate bot in a separate process
 */

// Checking if a pair argument is passed
if ($argc < 2) {
    echo "Usage: php BotRunner.php <pair>\n";
    exit(1);
}

// Getting the pair from the arguments
$pair = $argv[1];

// Creating a logger
$logger = Logger::getInstance();
$logger->log("Starting bot for pair {$pair} in a separate process");

// Registering a signal handler for proper termination
function handleSignal($signal) {
    global $logger, $pair, $bot;
    
    $logger->log("Bot for pair {$pair} received signal {$signal}, shutting down");
    
    // Clearing all orders when stopping
    if (isset($bot)) {
        $bot->clearAllOrders();
    }
    
    // Removing the PID file
    $pidFile = __DIR__ . '/../../data/pids/' . str_replace('_', '', $pair) . '.pid';
    if (file_exists($pidFile)) {
        unlink($pidFile);
    }
    
    exit(0);
}

// Registering signal handlers
pcntl_signal(SIGTERM, 'handleSignal');
pcntl_signal(SIGINT, 'handleSignal');
pcntl_signal(SIGHUP, 'handleSignal');

// Forcibly clearing and reloading the configuration
Config::reloadConfig();

// Checking if the pair exists and is active
$enabledPairs = Config::getEnabledPairs();
if (!in_array($pair, $enabledPairs)) {
    $logger->error("Pair {$pair} not found in active pairs, shutting down the bot");
    exit(1);
}

// Path to the configuration file
$configFile = __DIR__ . '/../../config/bots_config.json';
$lastConfigModTime = file_exists($configFile) ? filemtime($configFile) : 0;

try {
    // Getting the configuration for the pair
    $pairConfig = Config::getPairConfig($pair);
    
    if ($pairConfig === null) {
        $logger->error("Configuration for pair {$pair} not found");
        exit(1);
    }
    
    // Logging the values for verification
    $frequency_from = $pairConfig['settings']['frequency_from'];
    $frequency_to = $pairConfig['settings']['frequency_to'];
    
    $logger->log("Loaded configuration for {$pair}: frequency_from={$frequency_from}, frequency_to={$frequency_to}");
    
    // Creating a bot
    $bot = new TradingBot($pair, $pairConfig);
    
    // Clearing all orders when starting
    // $bot->clearAllOrders();
    
    // Initializing the bot
    $bot->initialize();
    
    // Main bot loop
    while (true) {
        try {
            // Forcibly reloading the configuration on each cycle
            Config::reloadConfig();
            
            // Checking if the configuration has changed
            if (file_exists($configFile)) {
                $currentModTime = filemtime($configFile);
                if ($currentModTime > $lastConfigModTime) {
                    $logger->log("Changes in the configuration detected, updating the bot settings");
                    
                    // Checking if the pair is still active
                    $enabledPairs = Config::getEnabledPairs();
                    if (!in_array($pair, $enabledPairs)) {
                        $logger->log("Pair {$pair} deactivated, shutting down the bot");
                        break;
                    }
                    
                    // Updating the configuration
                    $pairConfig = Config::getPairConfig($pair);
                    if ($pairConfig === null) {
                        $logger->error("Configuration for pair {$pair} not found");
                        break;
                    }
                    
                    // Update bot configuration
                    $bot->updateConfig();
                    
                    $frequency_from = $pairConfig['settings']['frequency_from'];
                    $frequency_to = $pairConfig['settings']['frequency_to'];
                    
                    $logger->log("Updated configuration for {$pair}: frequency_from={$frequency_from}, frequency_to={$frequency_to}");
                    
                    $lastConfigModTime = $currentModTime;
                }
            }
            
            // Running a single cycle of the bot
            $bot->runSingleCycle();
            
            // Forcibly getting the latest configuration before the delay
            Config::reloadConfig();
            $pairConfig = Config::getPairConfig($pair);
            
            if ($pairConfig === null) {
                $logger->error("Configuration for pair {$pair} not found before delay");
                break;
            }
            
            // Delay between cycles (in seconds)
            $frequency_from = $pairConfig['settings']['frequency_from'];
            $frequency_to = $pairConfig['settings']['frequency_to'];
            
            // If both frequencies are 0, skip the delay
            if ($frequency_from === 0 && $frequency_to === 0) {
                $delay = 0;
            } else {
                $minDelay = max(0, (int)$frequency_from);
                $maxDelay = max($minDelay, (int)$frequency_to);
                $delay = mt_rand($minDelay, $maxDelay);
            }
            
            $logger->log("Bot for pair {$pair} waiting {$delay} seconds for the next cycle");
            
            // Splitting the delay into short intervals to react faster to changes
            $shortInterval = 1; // 5 seconds
            $remainingDelay = $delay;
            
            while ($remainingDelay > 0) {
                $sleepTime = min($shortInterval, $remainingDelay);
                sleep($sleepTime);
                $remainingDelay -= $sleepTime;
                
                // Forcibly reloading the configuration
                Config::reloadConfig();
                
                // Checking if the pair is still active
                $enabledPairs = Config::getEnabledPairs();
                if (!in_array($pair, $enabledPairs)) {
                    $logger->log("Pair {$pair} deactivated during waiting, shutting down the bot");
                    break 2; // Exiting both loops
                }
                
                // Checking if the configuration has changed during waiting
                if (file_exists($configFile)) {
                    $currentModTime = filemtime($configFile);
                    if ($currentModTime > $lastConfigModTime) {
                        $logger->log("Changes in the configuration detected during waiting");
                        
                        // Updating the configuration
                        $pairConfig = Config::getPairConfig($pair);
                        if ($pairConfig === null) {
                            $logger->error("Configuration for pair {$pair} not found");
                            break 2; // Exiting both loops
                        }
                        
                        $lastConfigModTime = $currentModTime;
                        $logger->log("Changes in the configuration detected during waiting, continuing work");
                        break; // Exiting the inner loop and starting a new cycle of the bot
                    }
                }
            }
        } catch (Exception $e) {
            $logger->error("Error in the bot cycle for pair {$pair}: " . $e->getMessage());
            // Додаємо логування стек трейсу для відстеження джерела помилки
            $logger->logStackTrace("Stack trace for bot cycle error for pair {$pair}:");
            // Delay before retrying
            sleep(10);
        }
    }
    
    // Clearing all orders when stopping
    $bot->clearAllOrders();
    
    $logger->log("Bot for pair {$pair} stopped");
} catch (Exception $e) {
    $logger->error("Critical error in the bot for pair {$pair}: " . $e->getMessage());
    // Додаємо логування стек трейсу для відстеження джерела критичної помилки
    $logger->logStackTrace("Stack trace for critical error in bot for pair {$pair}:");
    exit(1);
} 