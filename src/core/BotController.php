<?php

declare(strict_types=1);

require_once __DIR__ . '/BotProcess.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/Logger.php';

class BotController
{
    private $logger;
    private $botProcess;
    
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->botProcess = new BotProcess();
    }
    
    /**
     * Show help
     */
    public function showHelp(): void
    {
        echo "Usage:\n";
        echo "  php BotController.php <command> [arguments]\n\n";
        echo "Available commands:\n";
        echo "  start <pair>       - Start the bot for the specified pair\n";
        echo "  stop <pair>        - Stop the bot for the specified pair\n";
        echo "  restart <pair>     - Restart the bot for the specified pair\n";
        echo "  start-all          - Start all bots for active pairs\n";
        echo "  stop-all           - Stop all bots\n";
        echo "  status [pair]      - Check the status of the bot for a pair or all bots\n";
        echo "  list               - List active pairs in the configuration\n";
        echo "  check-config       - Check configuration\n";
        echo "  update-processes   - Update all bot processes according to current configuration\n";
        echo "  start-manager      - Start the bot process manager\n";
        echo "  help               - Show this help message\n";
    }
    
    /**
     * Start the bot for a pair
     */
    public function startBot(string $pair): void
    {
        $this->logger->log("Starting bot for pair {$pair}");
        
        if (empty($pair)) {
            echo "Error: Pair not specified\n";
            exit(1);
        }
        
        // Check if the pair exists and is active
        Config::reloadConfig();
        $enabledPairs = Config::getEnabledPairs();
        
        if (!in_array($pair, $enabledPairs)) {
            echo "Error: Pair {$pair} is not active or does not exist in the configuration\n";
            exit(1);
        }
        
        if ($this->botProcess->isProcessRunning($pair)) {
            echo "Bot for pair {$pair} is already running\n";
        } else {
            if ($this->botProcess->startProcess($pair)) {
                echo "Bot for pair {$pair} successfully started\n";
            } else {
                echo "Error: Failed to start bot for pair {$pair}\n";
                exit(1);
            }
        }
    }
    
    /**
     * Stop the bot for a pair
     */
    public function stopBot(string $pair): void
    {
        $this->logger->log("Stopping bot for pair {$pair}");
        
        if (empty($pair)) {
            echo "Error: Pair not specified\n";
            exit(1);
        }
        
        if (!$this->botProcess->isProcessRunning($pair)) {
            echo "Bot for pair {$pair} is not running\n";
        } else {
            if ($this->botProcess->stopProcess($pair)) {
                echo "Bot for pair {$pair} successfully stopped\n";
            } else {
                echo "Error: Failed to stop bot for pair {$pair}\n";
                exit(1);
            }
        }
    }
    
    /**
     * Restart the bot for a pair
     */
    public function restartBot(string $pair): void
    {
        $this->logger->log("Restarting bot for pair {$pair}");
        
        if (empty($pair)) {
            echo "Error: Pair not specified\n";
            exit(1);
        }
        
        // Check if the pair exists and is active
        Config::reloadConfig();
        $enabledPairs = Config::getEnabledPairs();
        
        if (!in_array($pair, $enabledPairs)) {
            echo "Error: Pair {$pair} is not active or does not exist in the configuration\n";
            exit(1);
        }
        
        // Stop the bot if running
        if ($this->botProcess->isProcessRunning($pair)) {
            echo "Stopping bot for pair {$pair}...\n";
            if (!$this->botProcess->stopProcess($pair)) {
                echo "Error: Failed to stop bot for pair {$pair}\n";
                exit(1);
            }
            // Delay before starting
            sleep(2);
        }
        
        // Start the bot
        echo "Starting bot for pair {$pair}...\n";
        if ($this->botProcess->startProcess($pair)) {
            echo "Bot for pair {$pair} successfully restarted\n";
        } else {
            echo "Error: Failed to start bot for pair {$pair}\n";
            exit(1);
        }
    }
    
    /**
     * Start all bots
     */
    public function startAllBots(): void
    {
        $this->logger->log("Starting all bots for active pairs");
        
        // First, update the configuration
        Config::reloadConfig();
        
        // Get active pairs
        $enabledPairs = Config::getEnabledPairs();
        
        if (empty($enabledPairs)) {
            echo "No active pairs in configuration\n";
            exit(0);
        }
        
        echo "Starting bots for the following pairs: " . implode(", ", $enabledPairs) . "\n";
        
        // Start bots for each active pair
        $successCount = 0;
        $failCount = 0;
        
        foreach ($enabledPairs as $pair) {
            echo "Starting bot for pair {$pair}...\n";
            
            if ($this->botProcess->isProcessRunning($pair)) {
                echo "Bot for pair {$pair} is already running\n";
                $successCount++;
            } else {
                if ($this->botProcess->startProcess($pair)) {
                    echo "Bot for pair {$pair} successfully started\n";
                    $successCount++;
                    // Small delay between starts
                    usleep(500000); // 0.5 seconds
                } else {
                    echo "Error: Failed to start bot for pair {$pair}\n";
                    $failCount++;
                }
            }
        }
        
        echo "Bots startup completed. Successful: {$successCount}, with errors: {$failCount}\n";
    }
    
    /**
     * Stop all bots
     */
    public function stopAllBots(): void
    {
        $this->logger->log("Stopping all bots");
        $this->botProcess->stopAllProcesses();
        echo "All bots stopped\n";
    }
    
    /**
     * Check bot status
     */
    public function checkStatus(?string $pair = null): void
    {
        $this->logger->log("Checking bot status");
        
        // If a specific pair is specified
        if (!empty($pair)) {
            if ($this->botProcess->isProcessRunning($pair)) {
                echo "Bot for pair {$pair} is running\n";
            } else {
                echo "Bot for pair {$pair} is not running\n";
            }
            return;
        }
        
        // Check all active pairs
        Config::reloadConfig();
        $enabledPairs = Config::getEnabledPairs();
        
        if (empty($enabledPairs)) {
            echo "No active pairs in configuration\n";
            return;
        }
        
        echo "Bots status for active pairs:\n";
        echo "--------------------------------\n";
        
        $runningCount = 0;
        $stoppedCount = 0;
        
        foreach ($enabledPairs as $pair) {
            $status = $this->botProcess->isProcessRunning($pair) ? "running" : "stopped";
            echo "{$pair}: {$status}\n";
            
            if ($status === "running") {
                $runningCount++;
            } else {
                $stoppedCount++;
            }
        }
        
        echo "--------------------------------\n";
        echo "Total: " . count($enabledPairs) . ", running: {$runningCount}, stopped: {$stoppedCount}\n";
    }
    
    /**
     * List active pairs
     */
    public function listActivePairs(): void
    {
        Config::reloadConfig();
        $enabledPairs = Config::getEnabledPairs();
        
        if (empty($enabledPairs)) {
            echo "No active pairs in configuration\n";
            return;
        }
        
        echo "Active pairs in configuration:\n";
        echo "----------------------------\n";
        
        foreach ($enabledPairs as $pair) {
            $pairConfig = Config::getPairConfig($pair);
            
            if ($pairConfig) {
                $frequency_from = $pairConfig['settings']['frequency_from'];
                $frequency_to = $pairConfig['settings']['frequency_to'];
                
                echo "{$pair}: frequency_from={$frequency_from}, frequency_to={$frequency_to}\n";
            } else {
                echo "{$pair}: configuration not found\n";
            }
        }
    }
    
    /**
     * Check configuration
     */
    public function checkConfig(): void
    {
        $this->logger->log("Checking configuration");
        
        try {
            Config::reloadConfig();
            $configFile = __DIR__ . '/../../config/bots_config.json';
            
            if (!file_exists($configFile)) {
                echo "Error: Configuration file not found: {$configFile}\n";
                exit(1);
            }
            
            $configString = file_get_contents($configFile);
            
            if (empty($configString)) {
                echo "Error: Configuration file is empty\n";
                exit(1);
            }
            
            $config = json_decode($configString, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "Error: Invalid JSON format in configuration file: " . json_last_error_msg() . "\n";
                exit(1);
            }
            
            $enabledPairs = Config::getEnabledPairs();
            
            echo "Configuration checked successfully\n";
            echo "Configuration file: {$configFile}\n";
            echo "Last update: " . date('Y-m-d H:i:s', filemtime($configFile)) . "\n";
            echo "Active pairs: " . implode(", ", $enabledPairs) . "\n";
            
        } catch (Exception $e) {
            echo "Error: Failed to check configuration: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    /**
     * Update bot processes
     */
    public function updateProcesses(): void
    {
        $this->logger->log("Updating bot processes");
        
        try {
            $this->botProcess->updateProcesses();
            echo "Bot processes updated successfully\n";
            
        } catch (Exception $e) {
            echo "Error: Failed to update bot processes: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    /**
     * Start the bot process manager
     */
    public function startManager(): void
    {
        $this->logger->log("Starting bot process manager");
        
        // Check if the manager is already running
        $scriptPath = __DIR__ . '/TradingBotProcessManager.php';
        
        if (!file_exists($scriptPath)) {
            echo "Error: Bot process manager script not found: {$scriptPath}\n";
            exit(1);
        }
        
        // Command to start the bot process manager in the background
        $command = sprintf(
            'php %s > /dev/null 2>&1 & echo $!',
            escapeshellarg($scriptPath)
        );
        
        echo "Starting bot process manager...\n";
        $pid = exec($command);
        
        if (empty($pid)) {
            echo "Error: Failed to start bot process manager\n";
            exit(1);
        }
        
        echo "Bot process manager started successfully with PID: {$pid}\n";
    }
}

// Execute command
if ($argc < 2) {
    $controller = new BotController();
    $controller->showHelp();
    exit(0);
}

$controller = new BotController();
$command = $argv[1];

switch ($command) {
    case 'start':
        if ($argc < 3) {
            echo "Error: Pair not specified\n";
            exit(1);
        }
        $controller->startBot($argv[2]);
        break;
    
    case 'stop':
        if ($argc < 3) {
            echo "Error: Pair not specified\n";
            exit(1);
        }
        $controller->stopBot($argv[2]);
        break;
    
    case 'restart':
        if ($argc < 3) {
            echo "Error: Pair not specified\n";
            exit(1);
        }
        $controller->restartBot($argv[2]);
        break;
    
    case 'start-all':
        $controller->startAllBots();
        break;
    
    case 'stop-all':
        $controller->stopAllBots();
        break;
    
    case 'status':
        $pair = ($argc >= 3) ? $argv[2] : null;
        $controller->checkStatus($pair);
        break;
    
    case 'list':
        $controller->listActivePairs();
        break;
    
    case 'check-config':
        $controller->checkConfig();
        break;
    
    case 'update-processes':
        $controller->updateProcesses();
        break;
    
    case 'start-manager':
        $controller->startManager();
        break;
    
    case 'help':
        $controller->showHelp();
        break;
    
    default:
        echo "Unknown command: {$command}\n";
        $controller->showHelp();
        exit(1);
} 