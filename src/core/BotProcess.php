<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/Logger.php';

/**
 * Class for managing bot processes
 */
class BotProcess
{
    private Logger $logger;
    private string $pidDir;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->pidDir = __DIR__ . '/../../data/pids';
        
        // Create the directory for PID files if it doesn't exist
        if (!is_dir($this->pidDir)) {
            mkdir($this->pidDir, 0755, true);
        }
    }
    
    /**
     * Starting a process for a pair
     *
     * @param string $pair Trading pair
     * @return bool Whether the process was started successfully
     */
    public function startProcess(string $pair): bool
    {
        // Перевірка на порожню пару
        if (empty($pair)) {
            $this->logger->error("Cannot start process for empty pair");
            return false;
        }
        
        // Check if the process is already running for this pair
        if ($this->isProcessRunning($pair)) {
            $this->logger->log("Process for pair {$pair} is already running");
            return true;
        }
        
        // // Додаткова перевірка запущених процесів для цієї пари
        $runningCount = $this->countRunningProcessesForPair($pair);
        if ($runningCount > 0) {
            $this->logger->log("Виявлено {$runningCount} запущених процесів для пари {$pair}. Зупиняємо їх перед запуском нового.");
            $this->killAllProcessesForPair($pair);
            // Зачекаємо трохи, щоб процеси завершились
            sleep(1);
        }
        
        // Path to the script that will be executed in a separate process
        $scriptPath = __DIR__ . '/BotRunner.php';
        
        // Format the pair for the PID file name
        $safePair = $this->formatPairForFileName($pair);
        
        // Command to start the process
        $command = sprintf(
            'php %s %s > /dev/null 2>&1 & echo $!',
            escapeshellarg($scriptPath),
            escapeshellarg($pair)
        );
        
        // Execute the command and get the PID of the process
        $pid = exec($command);
        
        if (empty($pid)) {
            $this->logger->error("Failed to start process for pair {$pair}");
            return false;
        }
        
        // Save the PID of the process
        $this->savePid($safePair, (int) $pid);
        
        $this->logger->log("Started process for pair {$pair} (PID: {$pid})");
        return true;
    }
    
    /**
     * Stopping a process for a pair
     *
     * @param string $pair Trading pair
     * @return bool Result of stopping the process
     */
    public function stopProcess(string $pair): bool
    {
        $safePair = $this->formatPairForFileName($pair);
        $pid = $this->getPid($safePair);
        
        if ($pid === null) {
            $this->logger->log("Process for pair {$pair} not found");
            return false;
        }
        
        // Check if the process is still running
        if (!$this->isPidRunning($pid)) {
            $this->logger->log("Process for pair {$pair} (PID: {$pid}) is already stopped");
            $this->removePidFile($safePair);
            return true;
        }
        
        // Stop the process
        exec("kill {$pid}");
        
        // Wait for the process to finish (maximum 5 seconds)
        $maxWait = 5;
        $waited = 0;
        while ($this->isPidRunning($pid) && $waited < $maxWait) {
            sleep(1);
            $waited++;
        }
        
        // If the process is still running, forcefully terminate it
        if ($this->isPidRunning($pid)) {
            exec("kill -9 {$pid}");
            $this->logger->log("Process for pair {$pair} (PID: {$pid}) forcefully terminated");
        } else {
            $this->logger->log("Process for pair {$pair} (PID: {$pid}) stopped");
        }
        
        // Remove the PID file
        $this->removePidFile($safePair);
        
        return true;
    }
    
    /**
     * Checking if the process is running for a pair
     *
     * @param string $pair Trading pair
     * @return bool Whether the process is running
     */
    public function isProcessRunning(string $pair): bool
    {
        $safePair = $this->formatPairForFileName($pair);
        $pidFile = $this->getPidFilePath($safePair);
        
        if (!file_exists($pidFile)) {
            return false;
        }
        
        $pid = (int)file_get_contents($pidFile);
        
        // Check if the process is running
        if (file_exists("/proc/{$pid}")) {
            return true;
        }
        
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }
        
        $command = "ps -p {$pid} -o pid=";
        $output = [];
        exec($command, $output);
        
        return !empty($output);
    }
    
    /**
     * Checking if the process with the specified PID is running
     *
     * @param int $pid PID of the process
     * @return bool Whether the process is running
     */
    private function isPidRunning(int $pid): bool
    {
        exec("ps -p {$pid} -o pid=", $output);
        return !empty($output);
    }
    
    /**
     * Getting the PID of the process for a pair
     *
     * @param string $safePair Safe name of the pair for the file name
     * @return int|null PID of the process or null if the process is not found
     */
    private function getPid(string $safePair): ?int
    {
        $pidFile = $this->getPidFilePath($safePair);
        
        if (!file_exists($pidFile)) {
            return null;
        }
        
        $pid = (int) file_get_contents($pidFile);
        return $pid > 0 ? $pid : null;
    }
    
    /**
     * Saving the PID of the process for a pair
     *
     * @param string $safePair Safe name of the pair for the file name
     * @param int $pid PID of the process
     */
    private function savePid(string $safePair, int $pid): void
    {
        $pidFile = $this->getPidFilePath($safePair);
        file_put_contents($pidFile, $pid);
    }
    
    /**
     * Removing the PID file for a pair
     *
     * @param string $safePair Safe name of the pair for the file name
     */
    private function removePidFile(string $safePair): void
    {
        $pidFile = $this->getPidFilePath($safePair);
        
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
    }
    
    /**
     * Getting the path to the PID file for a pair
     *
     * @param string $safePair Safe name of the pair for the file name
     * @return string Path to the PID file
     */
    private function getPidFilePath(string $safePair): string
    {
        return $this->pidDir . '/' . $safePair . '.pid';
    }
    
    /**
     * Formatting the pair for the file name
     * 
     * @param string $pair Pair in the format BASE_QUOTE (for example, DOGE_BTC)
     * @return string Safe name for the file (for example, DOGEBTC)
     */
    protected function formatPairForFileName(string $pair): string
    {
        return str_replace('_', '', $pair);
    }
    
    /**
     * Restoring the original format of the pair from the file name
     * 
     * @param string $fileName File name (for example, DOGEBTC)
     * @return string Pair in the format BASE_QUOTE (for example, DOGE_BTC)
     */
    protected function restorePairFormat(string $fileName): string
    {
        // Searching for common currency pairs and their possible combinations
        $currencies = ['BTC', 'ETH', 'USDT', 'LTC', 'DOGE'];
        
        // Iterating through all possible combinations of currencies
        foreach ($currencies as $base) {
            foreach ($currencies as $quote) {
                if ($base === $quote) continue;
                
                $format = $base . $quote;
                // If the file name matches the format of the pair without an underscore
                if ($fileName === $format) {
                    return $base . '_' . $quote;
                }
            }
        }
        
        // If it is not possible to find a match, try to insert an underscore between the characters
        // This is not a very reliable method, but it may work as a backup option
        if (strlen($fileName) >= 6) {
            // Assuming the first 3 characters are the base currency
            return substr($fileName, 0, 3) . '_' . substr($fileName, 3);
        } else if (strlen($fileName) >= 4) {
            // Assuming the length is 4 or 5, we assume that the first 2 characters are the base currency
            return substr($fileName, 0, 2) . '_' . substr($fileName, 2);
        }
        
        $this->logger->error("Failed to restore the format of the pair from the file name: {$fileName}");
        return $fileName; // Returning the original name if it is not possible to restore the format
    }
    
    /**
     * Stopping all processes
     */
    public function stopAllProcesses(): void
    {
        $this->logger->log("Зупинка всіх процесів ботів...");
        
        // Getting the list of PID files
        $pidFiles = glob($this->pidDir . '/*.pid');
        
        foreach ($pidFiles as $pidFile) {
            $pairName = basename($pidFile, '.pid');
            $pair = $this->restorePairFormat($pairName);
            
            $this->stopProcess($pair);
        }
        
        // Checking if all processes are stopped
        $runningProcesses = `ps aux | grep BotRunner | grep -v grep`;
        if (!empty($runningProcesses)) {
            $this->logger->log("Detected running processes, stopping forcefully");
            exec("pkill -9 -f BotRunner.php");
        }
        
        // Cleaning all PID files
        array_map('unlink', glob($this->pidDir . '/*.pid'));
    }
    
    /**
     * Starting processes for all active pairs
     */
    public function startAllProcesses(): void
    {
        $this->logger->log("Starting processes for all active pairs...");
        
        // First, clean invalid PID files
        $this->cleanupInvalidPidFiles();
        
        // Reloading the configuration
        Config::reloadConfig();
        
        // Getting the list of active pairs
        $enabledPairs = Config::getEnabledPairs();
        
        // Stopping processes for inactive pairs
        $pidFiles = glob($this->pidDir . '/*.pid');
        foreach ($pidFiles as $pidFile) {
            $pairName = basename($pidFile, '.pid');
            $pair = $this->restorePairFormat($pairName);
            
            if (!in_array($pair, $enabledPairs)) {
                $this->logger->log("Pair {$pair} is disabled, stopping the process");
                $this->stopProcess($pair);
            }
        }
        
        // Starting processes for active pairs
        foreach ($enabledPairs as $pair) {
            if (!$this->isProcessRunning($pair)) {
                $this->startProcess($pair);
            }
        }
        
        $this->logger->log("All bot processes are started");
    }
    
    /**
     * Updating the list of processes
     */
    public function updateProcesses(): void
    {
        // Forcefully reload the configuration
        Config::reloadConfig();
        
        // Getting the list of active pairs
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("Active pairs: " . implode(", ", $enabledPairs));
        
        // Stopping processes for inactive pairs
        $pidFiles = glob($this->pidDir . '/*.pid');
        foreach ($pidFiles as $pidFile) {
            $pairName = basename($pidFile, '.pid');
            
            try {
                $pair = $this->restorePairFormat($pairName);
                
                if (!in_array($pair, $enabledPairs)) {
                    $this->logger->log("Pair {$pair} is disabled, stopping the process");
                    $this->stopProcess($pair);
                }
            } catch (Exception $e) {
                $this->logger->error("Error processing PID file {$pairName}: " . $e->getMessage());
                // Try to delete the problematic PID file
                if (file_exists($pidFile)) {
                    unlink($pidFile);
                }
            }
        }
        
        // Starting processes for new active pairs
        foreach ($enabledPairs as $pair) {
            try {
                if (!$this->isProcessRunning($pair)) {
                    $this->logger->log("Pair {$pair} is enabled, starting the process");
                    $this->startProcess($pair);
                }
            } catch (Exception $e) {
                $this->logger->error("Error starting the process for {$pair}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Cleaning invalid PID files
     */
    public function cleanupInvalidPidFiles(): void
    {
        $this->logger->log("Cleaning invalid PID files...");
        
        // Getting the list of PID files
        $pidFiles = glob($this->pidDir . '/*.pid');
        
        foreach ($pidFiles as $pidFile) {
            $pairName = basename($pidFile, '.pid');
            $pair = $this->restorePairFormat($pairName);
            
            if (file_exists($pidFile)) {
                $pid = (int)file_get_contents($pidFile);
                
                // Checking if the process with this PID exists and if it is our process
                $command = "ps -p $pid | grep BotRunner";
                exec($command, $output);
                
                if (empty($output)) {
                    $this->logger->log("Deleting invalid PID file for pair {$pair}");
                    unlink($pidFile);
                }
            }
        }
    }
    
    /**
     * Checking if the bot is active
     */
    private function isBotActive(string $pair): bool
    {
        // Getting the bot configuration
        $botConfig = Config::getPairConfig($pair);
        
        // Checking if the bot is active
        return isset($botConfig['isActive']) && $botConfig['isActive'] === true;
    }
    
    /**
     * Executing trading operations
     */
    private function executeTrades(string $pair): void
    {
        // ДОДАНО: Штучний лог для тестування системи логування
        $this->logger->info("======= ТЕСТОВИЙ ЛОГ: Запущено executeTrades для пари {$pair} =======");
        $this->logger->debug("DEBUG: Тестування рівня DEBUG в executeTrades");
        $this->logger->warning("WARNING: Тестування рівня WARNING в executeTrades");
        
        try {
            // Getting the bot configuration
            $botConfig = Config::getPairConfig($pair);
            
            if (!$botConfig) {
                $this->logger->error("Failed to find configuration for pair {$pair}");
                return;
            }
            $this->logger->log("111Bot configuration: " . json_encode($botConfig));
            
            // Checking the bot balance on the trade server before executing trades
            $botManager = new BotManager();
            $botId = $botConfig['id'] ?? 0;
            
            // Getting the currencies from the pair
            $currencies = explode('_', $pair);
            $baseCurrency = $currencies[0]; // The first currency in the pair (for example, ETH in ETH_USDT)
            
            // Checking the balance of the base currency
            $botBalance = $botManager->getBotBalanceFromTradeServer(Config::BOT_ID, $baseCurrency);
            $tradeAmountMax = $botConfig['trade_amount_max'];
            
            if ($botBalance < $tradeAmountMax) {
                $this->logger->error("[{$pair}] Insufficient balance for trading: need {$tradeAmountMax} {$baseCurrency}, available {$botBalance} {$baseCurrency}");
                
                // Updating the maximum trading amount to the available balance
                if ($botBalance > 0) {
                    $this->logger->log("[{$pair}] Automatically reducing the maximum trading amount to {$botBalance} {$baseCurrency}");
                    $botManager->updateBotTradeAmountMax($botId, $botBalance);
                } else {
                    $this->logger->error("[{$pair}] Trading is not possible due to lack of balance");
                    return;
                }
            }
            
        }
        catch (Exception $e) {
            $this->logger->error("Error executing trading operations for pair {$pair}: " . $e->getMessage());
            // Додаємо логування стек трейсу для відстеження джерела помилки
            $this->logger->logStackTrace("Stack trace for executing trading operations error:");
        }
    }
    
    /**
     * Підрахунок кількості запущених процесів для пари
     */
    private function countRunningProcessesForPair(string $pair): int
    {
        $escapedPair = escapeshellarg($pair);
        $command = "ps aux | grep BotRunner.php | grep {$escapedPair} | grep -v grep | wc -l";
        $count = (int)exec($command);
        return $count;
    }
    
    /**
     * Зупинка всіх процесів для пари
     */
    private function killAllProcessesForPair(string $pair): void
    {
        $escapedPair = escapeshellarg($pair);
        $command = "pkill -f \"BotRunner.php.*{$escapedPair}\"";
        exec($command);
    }
} 