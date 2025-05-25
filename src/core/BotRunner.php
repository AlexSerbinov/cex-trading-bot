<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/TradingBot.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/BotProcess.php';

/**
 * Class for running trading bots
 */
class BotRunner
{
    private $logger;
    private $pair;
    private $bot;
    private $terminate = false;
    private $configFile;
    private $mode; // 'bot' or 'manager'
    private $botProcess;
    private $lastCheckTime = 0;
    private $checkInterval = 5; // seconds
    private $lastPairConfigHash = null; // New field to store pair configuration hash

    /**
     * Constructor
     * 
     * @param string $pair Trading pair to run the bot for, or 'manager' for process manager mode
     */
    public function __construct(string $pair)
    {
        $this->pair = $pair;
        $this->logger = Logger::getInstance();
        $this->configFile = __DIR__ . '/../../config/bots_config.json';
        $this->botProcess = new BotProcess();
        
        // Check if we're running as a process manager or as a trading bot
        $this->mode = ($pair === 'manager') ? 'manager' : 'bot';
        
        if ($this->mode === 'manager') {
            $this->logger->log("BotRunner started in process manager mode");
        } else {
            $this->logger->log("BotRunner started in bot mode for pair {$this->pair}");
        }
        
        // Registering signal handlers
        $this->setupSignalHandlers();
    }

    /**
     * Set up signal handlers for proper termination
     */
    private function setupSignalHandlers()
    {
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        pcntl_signal(SIGHUP, [$this, 'handleSignal']);
    }

    /**
     * Signal handler for proper termination
     */
    public function handleSignal($signal)
    {
        if ($this->mode === 'manager') {
            $this->logger->log("Process manager received signal {$signal}, stopping all bots...");
            $this->botProcess->stopAllProcesses();
            $this->logger->log("All bots stopped, shutting down manager");
        } else {
            $this->logger->log("Bot for pair {$this->pair} received signal {$signal}, stopping");
            
            // Clearing all orders when stopping
            if (isset($this->bot)) {
                $this->bot->clearAllOrders();
            }
            
            // Removing the PID file
            $pidFile = __DIR__ . '/../../data/pids/' . str_replace('_', '', $this->pair) . '.pid';
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
        }
        
        $this->terminate = true;
        
        exit(0);
    }

    /**
     * Shut down the bot gracefully
     */
    private function shutdownGracefully()
    {
        if ($this->mode === 'manager') {
            $this->logger->log("Stopping all bot processes...");
            $this->botProcess->stopAllProcesses();
            $this->logger->log("All bots stopped, shutting down manager");
        } else {
            $this->logger->log("Stopping bot for pair {$this->pair}");
            
            // Clearing all orders when stopping
            if (isset($this->bot)) {
                $this->bot->clearAllOrders();
            }
            
            // Removing the PID file
            $pidFile = __DIR__ . '/../../data/pids/' . str_replace('_', '', $this->pair) . '.pid';
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
            
            $this->logger->log("Bot for pair {$this->pair} stopped");
        }
    }

    /**
     * Main execution loop of the trading bot.
     */
    public function run(): void
    {
        $this->logger->log("Starting bot for pair {$this->pair} in a separate process");

        // Forcibly clearing and reloading the configuration
        $this->logger->log("!!!!! BotRunner: Starting configuration reload before bot startup");
        Config::reloadConfig();
        $this->logger->log("!!!!! BotRunner: Configuration reloaded");

        // Checking if the pair exists and is active
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("!!!!! BotRunner: Active pairs in configuration: " . implode(", ", $enabledPairs));
        if (!in_array($this->pair, $enabledPairs)) {
            $this->logger->error("!!!!! BotRunner: Pair {$this->pair} not found in active pairs, stopping bot");
            exit(1);
        }

        // Path to the configuration file
        $lastConfigModTime = file_exists($this->configFile) ? filemtime($this->configFile) : 0;
        $this->logger->log("!!!!! BotRunner: Last modification time of configuration: " . date('Y-m-d H:i:s', $lastConfigModTime));

        try {
            // Getting the configuration for the pair
            $pairConfig = Config::getPairConfig($this->pair);
            
            if ($pairConfig === null) {
                $this->logger->error("!!!!! BotRunner: Configuration for pair {$this->pair} not found");
                exit(1);
            }
            
            // Initialize pair configuration hash
            $this->lastPairConfigHash = md5(json_encode($pairConfig));
            $this->logger->log("!!!!! BotRunner: Initial configuration hash for pair {$this->pair}: {$this->lastPairConfigHash}");
            
            // Logging the values for verification
            $frequency_from = $pairConfig['settings']['frequency_from'];
            $frequency_to = $pairConfig['settings']['frequency_to'];
            
            $this->logger->log("!!!!! BotRunner: Loaded configuration for {$this->pair}: frequency_from={$frequency_from}, frequency_to={$frequency_to}");
            $this->logger->log("!!!!! BotRunner: Full configuration for the bot: " . json_encode($pairConfig));
            
            // Creating a bot
            $this->logger->log("!!!!! BotRunner: Creating bot for pair {$this->pair}");
            $this->bot = new TradingBot($this->pair, $pairConfig);
            
            // Initializing the bot
            $this->logger->log("!!!!! BotRunner: Starting initialization of bot for pair {$this->pair}");
            $this->bot->initialize();
            $this->logger->log("!!!!! BotRunner: Initialization of bot for pair {$this->pair} completed");
            
            // Main bot loop
            while (!$this->terminate) {
                try {
                    // Forcibly reloading the configuration on each cycle
                    $this->logger->log("!!!!! BotRunner: Reloading configuration at the beginning of the cycle");
                    Config::reloadConfig();
                    
                    // Check if the pair is still active
                    $enabledPairs = Config::getEnabledPairs();
                    if (!in_array($this->pair, $enabledPairs)) {
                        $this->logger->log("!!!!! BotRunner: Pair {$this->pair} is deactivated, stopping bot");
                        break;
                    }
                    
                    // Get updated pair configuration
                    $pairConfig = Config::getPairConfig($this->pair);
                    if ($pairConfig === null) {
                        $this->logger->error("!!!!! BotRunner: Configuration for pair {$this->pair} not found during update");
                        break;
                    }
                    
                    // Calculate new configuration hash
                    $currentPairConfigHash = md5(json_encode($pairConfig));
                    
                    // Check if the configuration has changed
                    $configChanged = ($currentPairConfigHash !== $this->lastPairConfigHash);
                    
                    // Log hashes for debugging
                    $this->logger->log("!!!!! BotRunner: Current pair configuration hash: {$currentPairConfigHash}, previous: {$this->lastPairConfigHash}");
                    $this->logger->log("!!!!! BotRunner: Pair configuration changed: " . ($configChanged ? "YES" : "NO"));
                    
                    if ($configChanged) {
                        $this->logger->log("!!!!! BotRunner: Configuration change detected for pair {$this->pair}");
                        
                        $frequency_from = $pairConfig['settings']['frequency_from'];
                        $frequency_to = $pairConfig['settings']['frequency_to'];
                        
                        $this->logger->log("!!!!! BotRunner: Updated configuration for {$this->pair}: frequency_from={$frequency_from}, frequency_to={$frequency_to}");
                        $this->logger->log("!!!!! BotRunner: Full updated configuration for the bot: " . json_encode($pairConfig));
                        
                        // NEW LOGIC: Updating existing bot configuration
                        $this->logger->log("!!!!! BotRunner: Updating existing bot configuration");
                        
                        // Clear all orders before updating configuration
                        $this->logger->log("!!!!! BotRunner: Clearing all orders before updating configuration");
                        $this->bot->clearAllOrders();
                        
                        // Apply new configuration to the bot
                        $this->logger->log("!!!!! BotRunner: Applying new configuration to the bot");
                        $this->bot->updateConfig($pairConfig);
                        
                        // Reinitialize the bot with new configuration
                        $this->logger->log("!!!!! BotRunner: Reinitializing bot with updated configuration");
                        $this->bot->initialize();
                        $this->logger->log("!!!!! BotRunner: Reinitialization of bot with updated configuration completed");
                        
                        // Update configuration hash
                        $this->lastPairConfigHash = $currentPairConfigHash;
                    } else {
                        $this->logger->log("!!!!! BotRunner: Configuration for pair {$this->pair} not changed");
                    }
                    
                    // Running a single cycle of the bot
                    $this->logger->log("!!!!! BotRunner: Running a single cycle of the bot for pair {$this->pair}");
                    $this->bot->runSingleCycle();
                    $this->logger->log("!!!!! BotRunner: Bot cycle for pair {$this->pair} completed");
                    
                    // Forcibly getting the latest configuration before the delay
                    $this->logger->log("!!!!! BotRunner: Getting latest configuration before delay");
                    Config::reloadConfig();
                    $pairConfig = Config::getPairConfig($this->pair);
                    
                    if ($pairConfig === null) {
                        $this->logger->error("!!!!! BotRunner: Configuration for pair {$this->pair} not found before delay");
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
                    
                    $this->logger->log("!!!!! BotRunner: Bot for pair {$this->pair} waiting for {$delay} seconds until next cycle");
                    
                    // Splitting the delay into short intervals to react faster to changes
                    $shortInterval = 1; // 1 second
                    $remainingDelay = $delay;
                    
                    while ($remainingDelay > 0 && !$this->terminate) {
                        $sleepTime = min($shortInterval, $remainingDelay);
                        sleep($sleepTime);
                        $remainingDelay -= $sleepTime;
                        
                        // Forcibly reloading the configuration
                        $this->logger->log("!!!!! BotRunner: Checking configuration changes during delay, remaining delay: {$remainingDelay} sec.");
                        Config::reloadConfig();
                        
                        // Checking if the pair is still active
                        $enabledPairs = Config::getEnabledPairs();
                        if (!in_array($this->pair, $enabledPairs)) {
                            $this->logger->log("!!!!! BotRunner: Pair {$this->pair} deactivated during waiting, stopping bot");
                            break 2; // Exiting both loops
                        }
                        
                        // Checking if the pair configuration has changed
                        $pairConfig = Config::getPairConfig($this->pair);
                        if ($pairConfig !== null) {
                            $newPairConfigHash = md5(json_encode($pairConfig));
                            if ($newPairConfigHash !== $this->lastPairConfigHash) {
                                $this->logger->log("!!!!! BotRunner: Configuration change detected during waiting, interrupting waiting");
                                $this->logger->log("!!!!! BotRunner: New hash: {$newPairConfigHash}, old: {$this->lastPairConfigHash}");
                                $remainingDelay = 0; // Exiting the inner loop
                            }
                        }
                    }
                    
                    // Process any pending signals
                    pcntl_signal_dispatch();

                } catch (Exception $e) {
                    $this->logger->error("!!!!! BotRunner: Error during bot cycle for pair {$this->pair}: " . $e->getMessage());
                    $this->logger->error("!!!!! BotRunner: Stack trace: " . $e->getTraceAsString());
                    
                    // Sleeping for a short period before the next cycle in case of an error
                    sleep(5);
                }
            }
            
            $this->logger->log("!!!!! BotRunner: Bot for pair {$this->pair} finished");
            
        } catch (Exception $e) {
            $this->logger->error("!!!!! BotRunner: Critical error during bot startup for pair {$this->pair}: " . $e->getMessage());
            $this->logger->error("!!!!! BotRunner: Stack trace: " . $e->getTraceAsString());
            exit(1);
        }
        
        // Shutdown gracefully
        $this->shutdownGracefully();
    }

    /**
     * Run as a process manager that monitors and maintains bot processes
     */
    public function runAsManager(): void
    {
        $this->logger->log("Starting process manager for bots");
        
        // Forcibly reloading the configuration
        Config::reloadConfig();
        
        // Clean up old PID files
        $this->botProcess->cleanupInvalidPidFiles();
        
        // Start processes for all active pairs
        $this->logger->log("Initializing processes for all active pairs");
        $this->botProcess->startAllProcesses();
        
        // Main loop of the process manager
        $this->lastCheckTime = time();
        
        while (!$this->terminate) {
            try {
                // Processing signals
                pcntl_signal_dispatch();
                
                // Checking if it's time to update processes
                $currentTime = time();
                if (($currentTime - $this->lastCheckTime) >= $this->checkInterval) {
                    $this->logger->log("Process manager: checking process status...");
                    
                    // Checking if the configuration has changed
                    if (file_exists($this->configFile)) {
                        $configModTime = filemtime($this->configFile);
                        $this->logger->log("Process manager: last modification time of configuration: " . date('Y-m-d H:i:s', $configModTime));
                        
                        // Update processes according to current configuration
                        $this->logger->log("Process manager: updating processes...");
                        $this->botProcess->updateProcesses();
                        $this->logger->log("Process manager: processes updated");
                    }
                    
                    $this->lastCheckTime = $currentTime;
                }
                
                // Short sleep to prevent CPU overload
                sleep(1);
                
            } catch (Exception $e) {
                $this->logger->error("Process manager: error - " . $e->getMessage());
                $this->logger->error("Process manager: stack trace - " . $e->getTraceAsString());
                
                // Short sleep in case of an error
                sleep(5);
            }
        }
        
        $this->logger->log("Process manager finished");
        $this->shutdownGracefully();
    }
}

// Run script
if (count($argv) > 1) {
    $pair = $argv[1];
    $runner = new BotRunner($pair);
    $runner->run();
} 