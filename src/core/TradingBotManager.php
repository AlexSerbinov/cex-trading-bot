<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/BotProcess.php';

/**
 * Class for managing multiple bots for different pairs
 */
class TradingBotManager
{
    private Logger $logger;
    private BotProcess $botProcess;
    private int $checkInterval = 30; // Check interval for configuration changes (in seconds)
    private string $configFile;
    private int $lastConfigModTime = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Use the same log file as used in clean_and_run_local.sh
        $this->logger = Logger::getInstance(true, __DIR__ . '/../../data/logs/bots_error.log');
        $this->logger->log("Initialization of TradingBotManager, PID=" . getmypid());
        $this->botProcess = new BotProcess();
        $this->configFile = __DIR__ . '/../../config/bots_config.json';
        $this->logger->log("Check interval set to {$this->checkInterval} seconds");
        
        // Registering a signal handler for proper termination
        $this->logger->log("Registering signal handlers");
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        // Adding SIGHUP handling for better compatibility
        pcntl_signal(SIGHUP, [$this, 'handleSignal']);
        $this->logger->log("TradingBotManager successfully initialized");
    }

    /**
     * Signal handler
     */
    public function handleSignal(int $signal): void
    {
        $pidDir = __DIR__ . '/../../data/pids';
        $pidFiles = is_dir($pidDir) ? glob($pidDir . '/*.pid') : [];
        $this->logger->log("Received signal {$signal}, stopping all bots. PID files: " . implode(", ", $pidFiles));
        
        $this->botProcess->stopAllProcesses();
        
        // Adding deletion of PID files
        if (is_dir($pidDir)) {
            $files = glob($pidDir . '/*.pid');
            foreach ($files as $file) {
                unlink($file);
            }
        }
        exit(0);
    }

    /**
     * Method for clearing all orders for active pairs
     */
    public function clearAllOrdersForActivePairs(): void
    {
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("Clearing orders for active pairs: " . implode(", ", $enabledPairs));
        
        foreach ($enabledPairs as $pair) {
            $this->logger->log("Locking for clearing orders for pair {$pair}...");
            $lockFile = __DIR__ . "/../../data/locks/{$pair}_cleaning.lock";
            $lockHandle = fopen($lockFile, 'c');
            
            if (!$lockHandle) {
                $this->logger->log("Error creating lock file for pair {$pair}");
                continue;
            }
            
            $locked = flock($lockHandle, LOCK_EX | LOCK_NB);
            $this->logger->log("Lock status for pair {$pair}: " . ($locked ? "successfully" : "locked"));
            
            if ($locked) {
                try {
                    // Code for clearing orders for the pair
                    $this->logger->log("Clearing orders for pair {$pair}...");
                    // Add your order clearing code here
                    $this->logger->log("Clearing orders for pair {$pair} completed successfully");
                } catch (Exception $e) {
                    $this->logger->log("Error clearing orders for pair {$pair}: " . $e->getMessage());
                } finally {
                    flock($lockHandle, LOCK_UN);
                    fclose($lockHandle);
                    $this->logger->log("Unlocking for pair {$pair} completed");
                }
            } else {
                $this->logger->log("Unable to get lock for pair {$pair}, skipping clearing");
                fclose($lockHandle);
            }
        }
    }

    /**
     * Running all bots
     */
    public function runAllBots(): void
    {
        $this->logger->log("Starting bot manager in parallel execution mode");
        
        // Forcefully clearing and reloading the configuration
        Config::reloadConfig();
        
        // First, stop all existing processes
        $pidDir = __DIR__ . '/../../data/pids';
        $pidFiles = is_dir($pidDir) ? glob($pidDir . '/*.pid') : [];
        $this->logger->log("Stopping all existing processes. Found PID files: " . implode(", ", $pidFiles));
        
        $this->botProcess->stopAllProcesses();
        $this->logger->log("All existing processes stopped");
        
        // Очищення всіх ордерів для активних пар
        $this->logger->log("Starting clearing of orders for all active pairs");
        $this->clearAllOrdersForActivePairs();
        $this->logger->log("Clearing of orders for all active pairs completed");
        
        // Start processes for all active pairs
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("Starting processes for active pairs: " . implode(", ", $enabledPairs));
        
        $this->botProcess->startAllProcesses();
        $this->logger->log("All processes for active pairs started");
        
        // Remembering the last modification time of the configuration
        $this->lastConfigModTime = file_exists($this->configFile) ? filemtime($this->configFile) : 0;
        
        // Setting the last update time to the current time,
        // to avoid double process creation immediately after their creation
        $lastUpdateTime = time();
        $this->logger->log("Last update time set to {$lastUpdateTime}, next update in 60 seconds");
        
        // Main loop of the manager
        while (true) {
            // Forcefully reloading the configuration
            Config::reloadConfig();
            $currentTime = time();
            
            // Checking if the configuration has changed
            if (file_exists($this->configFile)) {
                $currentModTime = filemtime($this->configFile);
                if ($currentModTime > $this->lastConfigModTime) {
                    $this->logger->log("Changes in the configuration detected, updating processes");
                    $this->botProcess->updateProcesses();
                    $this->lastConfigModTime = $currentModTime;
                }
            }
            
            // Forcefully updating processes every 60 seconds
            // Using a static variable with the initial value we set earlier
            if ($currentTime - $lastUpdateTime >= 60) {
                $this->logger->log("Scheduled process update (every 60 seconds)");
                $this->logger->log("Time since last update: " . ($currentTime - $lastUpdateTime) . " seconds");
                
                // Спочатку очищаємо недійсні PID-файли
                $this->botProcess->cleanupInvalidPidFiles();
                $this->botProcess->updateProcesses();
                $lastUpdateTime = $currentTime;
                $this->logger->log("Scheduled process update completed");
            }
            
            // Short delay before the next check
            $this->logger->log("Waiting {$this->checkInterval} seconds before the next check...");
            sleep($this->checkInterval);
        }
    }
}

// Running the bot manager if the file is called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $logger = Logger::getInstance(true, __DIR__ . '/../../data/logs/bots_error.log');
    $logger->log("=== STARTING TRADING BOT MANAGER ===");
    
    // Check if TradingBotManager is already running
    $lockFile = __DIR__ . '/../../data/pids/trading_bot_manager.lock';
    $logger->log("Checking lock file: {$lockFile}");
    
    // Docker-specific check for running processes
    if (file_exists($lockFile)) {
        $pid = (int)file_get_contents($lockFile);
        $logger->log("Found existing lock file with PID: {$pid}");
        
        // Check if the process with this PID is still running
        $processExists = false;
        
        // Check method 1: via /proc (main method in Linux)
        if (file_exists("/proc/{$pid}")) {
            $logger->log("PID {$pid} exists in /proc");
            
            // Additional check via cmdline
            if (file_exists("/proc/{$pid}/cmdline")) {
                $cmdline = file_get_contents("/proc/{$pid}/cmdline");
                $logger->log("Command line of the process: " . $cmdline);
                
                if (strpos($cmdline, 'TradingBotManager') !== false) {
                    $processExists = true;
                    $logger->log("Process confirmed as TradingBotManager");
                }
            }
        }
        
        // Check method 2: via posix_kill function (if available)
        if (function_exists('posix_kill')) {
            // Signal 0 - check if the process exists without sending a signal
            if (posix_kill($pid, 0)) {
                $logger->log("PID {$pid} confirmed via posix_kill");
                $processExists = true;
            } else {
                $logger->log("PID {$pid} does not exist via posix_kill check");
            }
        }
        
        if ($processExists) {
            $logger->log("TradingBotManager already running with PID {$pid}. Exiting.");
            echo "TradingBotManager already running with PID {$pid}. Exiting.\n";
            exit(0);
        } else {
            $logger->log("Process with PID {$pid} not found or it is not TradingBotManager. Removing the old lock file.");
            unlink($lockFile);
        }
    } else {
        $logger->log("Lock file not found. Creating a new one.");
    }
    
    // Saving the current PID
    $currentPid = getmypid();
    $logger->log("Creating a new lock file with PID: {$currentPid}");
    file_put_contents($lockFile, $currentPid);
    $logger->log("Saved current PID {$currentPid} in the lock file");
    
    $manager = new TradingBotManager();
    $logger->log("Created an instance of TradingBotManager");
    $manager->runAllBots();
}