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
    private int $checkInterval = 10; // Check interval for configuration changes (in seconds)
    private string $configFile;
    private int $lastConfigModTime = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->botProcess = new BotProcess();
        $this->configFile = __DIR__ . '/../../data/bots_config.json';
        
        // Перевірка наявності функції pcntl_signal
        if (function_exists('pcntl_signal')) {
            // Встановлюємо обробники сигналів
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        } else {
            // Альтернативний код для середовищ без підтримки pcntl
            $this->logger->log("PCNTL extension not available. Signal handling disabled.");
        }
    }

    /**
     * Signal handler
     */
    public function handleSignal(int $signal): void
    {
        $this->logger->log("Received signal {$signal}, stopping all bots");
        $this->botProcess->stopAllProcesses();
        // Adding deletion of PID files
        $pidDir = __DIR__ . '/../../data/pids';
        if (is_dir($pidDir)) {
            $files = glob($pidDir . '/*.pid');
            foreach ($files as $file) {
                unlink($file);
            }
        }
        exit(0);
    }

    /**
     * Running all bots
     */
    public function runAllBots(): void
    {
        $this->logger->log("Starting the bot manager in parallel execution mode");
        
        // Forcefully clearing and reloading the configuration
        Config::reloadConfig();
        
        // First, stop all existing processes
        $this->botProcess->stopAllProcesses();
        
        // Start processes for all active pairs
        $this->botProcess->startAllProcesses();
        
        // Remembering the last modification time of the configuration
        $this->lastConfigModTime = file_exists($this->configFile) ? filemtime($this->configFile) : 0;
        
        // Main loop of the manager
        while (true) {
            // Forcefully reloading the configuration
            Config::reloadConfig();
            
            // Checking if the configuration has changed
            if (file_exists($this->configFile)) {
                $currentModTime = filemtime($this->configFile);
                if ($currentModTime > $this->lastConfigModTime) {
                    $this->logger->log("Changes in the configuration detected, updating processes");
                    $this->botProcess->updateProcesses();
                    $this->lastConfigModTime = $currentModTime;
                }
            }
            
            // Forcefully updating processes every 15 seconds
            static $lastUpdateTime = 0;
            $currentTime = time();
            if ($currentTime - $lastUpdateTime >= 15) {
                $this->botProcess->updateProcesses();
                $lastUpdateTime = $currentTime;
            }
            
            // Short delay before the next check
            sleep($this->checkInterval);
        }
    }
}

// Running the bot manager if the file is called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $manager = new TradingBotManager();
    $manager->runAllBots();
}
