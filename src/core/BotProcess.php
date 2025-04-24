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
    private array $pairConfigHashes = [];
    private $args;
    private $phpBinary;
    private $botScriptPath;
    /** @var array Hashes of configurations for each pair */
    private $configHashes = [];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Use path to regular log file
        $environment = getenv('ENVIRONMENT') ?: 'local';
        $logFile = __DIR__ . '/../../data/logs/' . $environment . '/bot.log';
        
        $this->logger = Logger::getInstance(true, $logFile);
        $this->pidDir = __DIR__ . '/../../data/pids';
        $this->logger->log("BotProcess: Initialization, path to PID directory: {$this->pidDir}");
        
        // Create the directory for PID files if it doesn't exist
        if (!is_dir($this->pidDir)) {
            mkdir($this->pidDir, 0755, true);
            $this->logger->log("BotProcess: Created directory for PID files: {$this->pidDir}");
        } else {
            $this->logger->log("BotProcess: Directory for PID files exists");
        }
        
        // Create the directory for lock files
        $lockDir = __DIR__ . '/../../data/locks';
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
            $this->logger->log("BotProcess: Created directory for lock files: {$lockDir}");
        } else {
            $this->logger->log("BotProcess: Directory for lock files exists");
        }
        
        // Initialize configuration hashes for all pairs
        $this->initPairConfigHashes();
    }
    
    /**
     * Initialize configuration hashes for all pairs
     */
    private function initPairConfigHashes(): void
    {
        $this->logger->log("!!!!! BotProcess::initPairConfigHashes(): Initializing configuration hashes");
        
        // Get all pairs from configuration
        $allPairs = Config::getAllPairs();
        
        foreach ($allPairs as $pair) {
            $pairConfig = Config::getPairConfig($pair);
            if ($pairConfig) {
                $this->pairConfigHashes[$pair] = md5(json_encode($pairConfig));
                $this->logger->log("!!!!! BotProcess::initPairConfigHashes(): Initialized hash for pair {$pair}");
            }
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
        // Check for empty pair
        if (empty($pair)) {
            $this->logger->error("Cannot start process for empty pair");
            return false;
        }
        
        $this->logger->log("!!!!! BotProcess::startProcess(): Starting process for pair {$pair}");
        
        // Check if the process is already running for this pair
        if ($this->isProcessRunning($pair)) {
            $this->logger->log("!!!!! BotProcess::startProcess(): Process for pair {$pair} is already running, skipping start");
            return true;
        }
        
        // Additional check for running processes for this pair
        // Using a more reliable check method
        $pidFile = $this->getPidFilePath($this->formatPairForFileName($pair));
        $this->logger->log("!!!!! BotProcess::startProcess(): Checking PID file for pair {$pair}: {$pidFile}");
        
        if (file_exists($pidFile)) {
            $pid = (int)file_get_contents($pidFile);
            $this->logger->log("!!!!! BotProcess::startProcess(): Found existing PID file for pair {$pair} with PID: {$pid}");
            
            if ($pid > 0 && file_exists("/proc/{$pid}")) {
                $this->logger->log("!!!!! BotProcess::startProcess(): Process {$pid} for pair {$pair} exists, stopping it before starting a new one");
                $this->stopProcess($pair);
                $this->logger->log("!!!!! BotProcess::startProcess(): Waiting 1 second after stopping process for pair {$pair}");
                sleep(1);
            } else {
                $this->logger->log("!!!!! BotProcess::startProcess(): PID file for pair {$pair} exists, but process {$pid} is not running, deleting the file");
                unlink($pidFile);
            }
        } else {
            $this->logger->log("!!!!! BotProcess::startProcess(): PID file for pair {$pair} does not exist");
        }
        
        // Checking for running BotRunner processes for this pair via proc
        $this->logger->log("!!!!! BotProcess::startProcess(): Checking for running BotRunner processes for pair {$pair}");
        $runningProcesses = $this->findRunningBotRunnerProcesses($pair);
        
        if (!empty($runningProcesses)) {
            $this->logger->log("!!!!! BotProcess::startProcess(): Found running BotRunner processes for pair {$pair}: " . implode(", ", $runningProcesses));
            foreach ($runningProcesses as $pid) {
                $this->logger->log("!!!!! BotProcess::startProcess(): Stopping process {$pid} for pair {$pair}");
                if (function_exists('posix_kill')) {
                    $killResult = posix_kill($pid, SIGTERM);
                    $this->logger->log("!!!!! BotProcess::startProcess(): posix_kill result for PID {$pid} pair {$pair}: " . ($killResult ? "success" : "failed"));
                } else {
                    exec("kill -15 {$pid}");
                    $this->logger->log("!!!!! BotProcess::startProcess(): Executed command kill -15 for PID {$pid} pair {$pair}");
                }
            }
            $this->logger->log("!!!!! BotProcess::startProcess(): Waiting 1 second after stopping processes for pair {$pair}");
            sleep(1);
        } else {
            $this->logger->log("!!!!! BotProcess::startProcess(): No running BotRunner processes found for pair {$pair}");
        }
        
        // Path to the script that will be executed in a separate process
        $scriptPath = __DIR__ . '/TradingBotRunner.php';
        
        // Format the pair for the PID file name
        $safePair = $this->formatPairForFileName($pair);
        
        // Acquire a lock to ensure no parallel process starts for this pair
        $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
        $this->logger->log("!!!!! BotProcess::startProcess(): Attempting to lock for pair {$pair}, lock file: {$lockFile}");
        
        // Checking if the lock file already exists and if it is locked
        if (file_exists($lockFile)) {
            $existingLockHandle = @fopen($lockFile, 'r');
            if ($existingLockHandle) {
                $locked = !flock($existingLockHandle, LOCK_EX | LOCK_NB);
                fclose($existingLockHandle);
                
                if ($locked) {
                    $this->logger->log("!!!!! BotProcess::startProcess(): Lock file for pair {$pair} is already locked. Another process is already running or starting.");
                    return false;
                } else {
                    $this->logger->log("!!!!! BotProcess::startProcess(): Lock file for pair {$pair} exists, but is not locked. Deleting it.");
                    unlink($lockFile);
                }
            }
        }
        
        $lockHandle = fopen($lockFile, 'c');
        
        if (!$lockHandle) {
            $this->logger->error("!!!!! BotProcess::startProcess(): Failed to create lock file for pair {$pair}");
            return false;
        }
        
        $locked = flock($lockHandle, LOCK_EX | LOCK_NB);
        $this->logger->log("!!!!! BotProcess::startProcess(): Lock status for pair {$pair}: " . ($locked ? "successfully" : "locked"));
        
        if (!$locked) {
            $this->logger->log("!!!!! BotProcess::startProcess(): Failed to get lock for pair {$pair}, perhaps another process is already starting");
            fclose($lockHandle);
            return false;
        }
        
        // Save the lock file handle to an additional file for later use
        $lockHandleFile = $this->pidDir . "/{$safePair}_lock_handle.txt";
        file_put_contents($lockHandleFile, "locked");
        
        try {
            // Use standard redirection to /dev/null
            // to run the process in the background
            $command = sprintf(
                'php %s %s > /dev/null 2>&1 & echo $!',
                escapeshellarg($scriptPath),
                escapeshellarg($pair)
            );
            
            $this->logger->log("!!!!! BotProcess::startProcess(): Executing command to start process for pair {$pair}: {$command}");
            
            // Execute the command and get the PID of the process
            $pid = exec($command);
            
            if (!$pid || !is_numeric($pid) || $pid <= 0) {
                $this->logger->error("!!!!! BotProcess::startProcess(): Error starting process for pair {$pair}, invalid PID: {$pid}");
                // flock($lockHandle, LOCK_UN); // REMOVED
                // fclose($lockHandle); // REMOVED
                return false;
            }
            
            $this->logger->log("!!!!! BotProcess::startProcess(): Process started for pair {$pair}, PID: {$pid}");
            
            // Saving the PID to a file
            $this->savePid($safePair, (int)$pid);
            
            // Releasing the lock - MOVED TO finally
            // flock($lockHandle, LOCK_UN); // REMOVED
            // fclose($lockHandle); // REMOVED
            
            // Checking if the process is actually running
            usleep(500000); // 0.5 second
            $this->logger->log("????? BotProcess::startProcess(): Checking if process {$pid} for pair {$pair} is successfully started");

            $processExists = false;
            if (function_exists('posix_kill')) {
                $processExists = posix_kill((int)$pid, 0); // Use posix_kill to check if process exists
                $this->logger->log("????? BotProcess::startProcess(): posix_kill check result for PID {$pid}: " . ($processExists ? "exists" : "does not exist"));
            } else {
                $this->logger->error("!!!!! BotProcess::startProcess(): posix_kill function is not available. Cannot reliably check status of process {$pid}.");
                // Optionally, assume it started if posix is not available, 
                // but this is less reliable.
                // $processExists = true; 
            }

            if ($processExists) {
                $this->logger->log("!!!!! BotProcess::startProcess(): Checked that process {$pid} for pair {$pair} is successfully started (using posix_kill)");
                return true;
            } else {
                $this->logger->error("!!!!! BotProcess::startProcess(): Process {$pid} for pair {$pair} did not start or exited quickly (checked via posix_kill)");
                $this->removePidFile($safePair);
                return false;
            }
            
        } finally {
            // Ensure the lock is always released and the handle closed
            if (is_resource($lockHandle)) {
                 $this->logger->log("!!!!! BotProcess::startProcess(): Releasing lock and closing lock file for pair {$pair} in finally block");
                 flock($lockHandle, LOCK_UN);
                 fclose($lockHandle);
            }
        }
    }
    
    /**
     * Stopping a process for a pair
     *
     * @param string $pair Trading pair
     * @return bool Whether the process was stopped successfully
     */
    public function stopProcess(string $pair): bool
    {
        $this->logger->log("!!!!! BotProcess::stopProcess(): Stopping process for pair {$pair}");
        
        // Format the pair for the PID file name
        $safePair = $this->formatPairForFileName($pair);
        
        // Path to the PID file
        $pidFile = $this->getPidFilePath($safePair);
        
        $this->logger->log("!!!!! BotProcess::stopProcess(): Getting PID file path for pair {$pair}: {$pidFile}");
        
        // If the PID file does not exist, the process is not running
        if (!file_exists($pidFile)) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): PID file for pair {$pair} not found");
            
            // Check if the process is running
            $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
            if (file_exists($lockFile)) {
                $this->logger->log("!!!!! BotProcess::stopProcess(): Process for pair {$pair} is not running");
                @unlink($lockFile);
            }
            
            return true;
        }
        
        // Reading the PID from the file
        $pid = (int)file_get_contents($pidFile);
        
        if ($pid <= 0) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): PID file {$pidFile} exists, but contains invalid PID: {$pid}");
            unlink($pidFile);
            
            // Remove lock file
            $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
            if (file_exists($lockFile)) {
                $this->logger->log("!!!!! BotProcess::stopProcess(): Removing lock file {$lockFile}");
                @unlink($lockFile);
            }
            
            return true;
        }
        
        $this->logger->log("!!!!! BotProcess::stopProcess(): Found PID {$pid} for pair {$pair}. Sending SIGTERM.");
        
        // Waiting for the process to exit
        $this->logger->log("!!!!! BotProcess::stopProcess(): Waiting for process {$pid} for pair {$pair} to terminate...");
        $maxWait = 5; // maximum wait time in seconds
        $waited = 0;
        $processExists = true; // Assume it exists initially
        while ($waited < $maxWait) {
            if (function_exists('posix_kill')) {
                $processExists = posix_kill($pid, 0); // Check using posix_kill
            } else {
                // Fallback: If posix_kill is not available, we can't reliably check.
                // We might break the loop earlier or just wait the full time.
                // Let's break assuming the kill command worked after a short delay.
                if ($waited > 1) { // Wait at least 1 second in this case
                   $this->logger->warning("!!!!! BotProcess::stopProcess(): posix_kill is not available. Cannot reliably check process status for PID {$pid}.");
                   $processExists = false; // Assume stopped
                }
            }
            
            if (!$processExists) {
                $this->logger->log("!!!!! BotProcess::stopProcess(): Process {$pid} for pair {$pair} did not terminate after SIGTERM. Sending SIGKILL.");
                break;
            }
            sleep(1);
            $waited++;
        }
        
        // If the process is still running, forcefully terminate it (SIGKILL)
        if ($processExists) { // Use the last known state from the loop
            $this->logger->log("!!!!! BotProcess::stopProcess(): Process {$pid} for pair {$pair} is still running after {$waited} seconds. Sending SIGKILL.");
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGKILL);
            } else {
                exec("kill -9 {$pid}");
            }
            $this->logger->log("!!!!! BotProcess::stopProcess(): posix_kill result for SIGKILL for PID {$pid} pair {$pair}: " . ($processExists ? "success" : "failed"));
            sleep(1);
        }
        
        // Removing the PID file
        if (file_exists($pidFile)) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): Removing PID file {$pidFile} for pair {$pair}");
            unlink($pidFile);
        }
        
        // Remove lock file
        $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
        if (file_exists($lockFile)) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): Removing lock file {$lockFile}");
            @unlink($lockFile);
        }
        
        // Remove lock file handle
        $lockHandleFile = $this->pidDir . "/{$safePair}_lock_handle.txt";
        if (file_exists($lockHandleFile)) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): Removing lock file handle {$lockHandleFile}");
            @unlink($lockHandleFile);
        }
        
        // Verifying that the process is no longer running
        $finalCheckExists = false;
        if (function_exists('posix_kill')) {
             $finalCheckExists = posix_kill($pid, 0);
        }
        // If posix_kill is not available, we cannot perform a reliable final check.
        
        if ($finalCheckExists) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): Warning: Process {$pid} for pair {$pair} is still running after all attempts to stop it (checked via posix_kill)");
            return false;
        }
        
        $this->logger->log("!!!!! BotProcess::stopProcess(): Process for pair {$pair} stopped successfully");
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
        $this->logger->log("!!!!! BotProcess::isProcessRunning(): Checking if process for pair {$pair} is running");
        
        // Format the pair for the PID file name
        $safePair = $this->formatPairForFileName($pair);
        
        // Path to the PID file
        $pidFile = $this->getPidFilePath($safePair);
        
        $this->logger->log("!!!!! BotProcess::isProcessRunning(): Getting PID file path for pair {$pair}: {$pidFile}");
        
        // Check if the process is running
        $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
        if (file_exists($lockFile)) {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Found lock file for pair {$pair}: {$lockFile}");
            
            // Check if the lock file is locked
            $lockHandle = @fopen($lockFile, 'r');
            if ($lockHandle) {
                $lockStatus = !flock($lockHandle, LOCK_EX | LOCK_NB);
                fclose($lockHandle);
                
                if ($lockStatus) {
                    $this->logger->log("!!!!! BotProcess::isProcessRunning(): Lock file for pair {$pair} is locked, process is considered running");
                    
                    // If the PID file exists, but the lock file is locked, this is good
                    // If the PID file does not exist, but the lock file is locked, something is wrong
                    if (!file_exists($pidFile)) {
                        $this->logger->log("!!!!! BotProcess::isProcessRunning(): Warning: Lock file for pair {$pair} is locked, but PID file does not exist");
                        // Try to recover the PID file from the lock file
                        $pid = file_get_contents($lockFile);
                        if (is_numeric($pid) && $pid > 0) {
                            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Recovering PID file for pair {$pair} from lock file, PID: {$pid}");
                            $this->savePid($safePair, (int) $pid);
                        }
                    }
                    
                    return true;
                } else {
                    $this->logger->log("!!!!! BotProcess::isProcessRunning(): Lock file for pair {$pair} is not locked, checking PID file further");
                }
            }
        } else {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Lock file for pair {$pair} not found");
        }
        
        // If the PID file does not exist, the process is not running
        if (!file_exists($pidFile)) {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): PID file for pair {$pair} not found");
            return false;
        }
        
        // Reading the PID from the file
        $pid = (int)file_get_contents($pidFile);
        
        if ($pid <= 0) {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Invalid PID ({$pid}) in file for pair {$pair}");
            // Remove invalid PID file
            unlink($pidFile);
            return false;
        }
        
        $this->logger->log("!!!!! BotProcess::isProcessRunning(): Found PID: {$pid} for pair {$pair}");
        
        // Checking if the process is running using posix_kill (cross-platform)
        $processExists = false;
        if (function_exists('posix_kill')) {
            $processExists = posix_kill($pid, 0);
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Checking process {$pid} via posix_kill: " . ($processExists ? "exists" : "does not exist"));
        } else {
            $this->logger->error("!!!!! BotProcess::isProcessRunning(): posix_kill function is not available. Cannot reliably check process status for PID {$pid}.");
            // Cannot reliably check, maybe return true to avoid constant restarts? Or false?
            // Returning false is safer to avoid stale PID files if posix isn't available.
            unlink($pidFile);
            return false;
        }

        if ($processExists) {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Process with PID {$pid} for pair {$pair} is running.");
            // Optional: Could add a check here if the lock file exists and is locked,
            // and try to relock/recreate PID if inconsistencies are found.
            return true;
        } else {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Process with PID {$pid} does not exist (checked via posix_kill)");
        }
        
        // If we got here, the PID file exists, but the process doesn't
        $this->logger->log("!!!!! BotProcess::isProcessRunning(): Removing stale PID file {$pidFile} for pair {$pair}");
        unlink($pidFile);
        
        // Also remove the lock file if it exists
        if (file_exists($lockFile)) {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Removing lock file {$lockFile} for pair {$pair}");
            @unlink($lockFile);
        }
        
        return false;
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
        $this->logger->log("Stopping all bot processes...");
        $files = glob("{$this->pidDir}/*.pid");
        
        $this->logger->log("Found PID files for stopping: " . implode(", ", $files));
        
        foreach ($files as $file) {
            $pairName = basename($file, '.pid');
            $pair = $this->restorePairFormat($pairName);
            $this->logger->log("Stopping process for pair {$pair}, PID file: {$file}");
            $pid = (int)file_get_contents($file);
            $this->logger->log("PID for stopping: {$pid}, pair: {$pair}");
            
            if (posix_kill($pid, SIGTERM)) {
                $this->logger->log("Sent SIGTERM signal to process {$pid} for pair {$pair}");
                $startTime = time();
                while (posix_kill($pid, 0) && (time() - $startTime) < 5) {
                    usleep(100000);
                }
                if (!posix_kill($pid, 0)) {
                    $this->logger->log("Process {$pid} for pair {$pair} completed");
                } else {
                    $this->logger->log("Process {$pid} for pair {$pair} did not complete, killing forcefully");
                    posix_kill($pid, SIGKILL);
                }
            } else {
                $this->logger->log("Failed to send SIGTERM to process {$pid} for pair {$pair}, possibly already completed");
            }
            
            unlink($file);
            @unlink("{$this->pidDir}/{$pairName}.lock"); // Remove lock file
            $this->logger->log("Process for pair {$pair} stopped, PID file and lock removed");
        }
        
        $this->logger->log("All bot processes stopped");
    }
    
    /**
     * Starting processes for all active pairs
     */
    public function startAllProcesses(): void
    {
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("Starting processes for all active pairs: " . implode(", ", $enabledPairs));
        
        // First, clean invalid PID files
        $this->cleanupInvalidPidFiles();
        $this->logger->log("Cleaned invalid PID files");
        
        // Reloading the configuration
        Config::reloadConfig();
        $this->logger->log("Reloaded configuration");
        
        // Getting the list of active pairs
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("Active pairs after reloading configuration: " . implode(", ", $enabledPairs));
        
        // Stopping processes for inactive pairs
        $pidFiles = glob($this->pidDir . '/*.pid');
        $this->logger->log("Existing PID files: " . implode(", ", $pidFiles));
        
        foreach ($pidFiles as $pidFile) {
            $pairName = basename($pidFile, '.pid');
            $pair = $this->restorePairFormat($pairName);
            $this->logger->log("Checking pair from PID file: {$pair}");
            
            if (!in_array($pair, $enabledPairs)) {
                $this->logger->log("Pair {$pair} disabled, stopping process");
                $this->stopProcess($pair);
            } else {
                $this->logger->log("Pair {$pair} active, leaving process");
            }
        }
        
        // Starting processes for active pairs
        foreach ($enabledPairs as $pair) {
            if (!$this->isProcessRunning($pair)) {
                $this->logger->log("Pair {$pair} active, but process not running. Starting process.");
                $this->startProcess($pair);
            } else {
                $this->logger->log("Pair {$pair} active, process already running.");
            }
        }
        
        $this->logger->log("All bot processes for active pairs started");
    }
    
    /**
     * Updating the list of processes
     */
    public function updateProcesses(): void
    {
        $this->logger->info("!!!!! Updating processes based on active pairs");
        
        // Get a list of all configured pairs
        $pairs = Config::getEnabledPairs();
        $this->logger->info("!!!!! Active pairs: " . implode(", ", $pairs ?: []));
        
        // Get a list of currently running pairs from PID files
        $runningProcesses = $this->getRunningProcesses();
        
        // Check each active pair
        foreach ($pairs as $pair) {
            // Get current configuration hash for the pair
            $config = Config::getPairConfig($pair);
            
            if (empty($config)) {
                $this->logger->error("!!!!! Configuration missing for pair $pair");
                continue;
            }
            
            $configHash = md5(json_encode($config));
            $oldConfigHash = $this->pairConfigHashes[$pair] ?? null;
            
            $this->logger->info("!!!!! Pair: $pair, Old hash: " . ($oldConfigHash ?: 'missing') . ", New hash: $configHash");
            
            // Check if the configuration changed or if the process is not running
            if ($oldConfigHash !== $configHash || !in_array($pair, $runningProcesses)) {
                // If the process is already running, stop it before restarting
                if (in_array($pair, $runningProcesses)) {
                    $this->logger->info("!!!!! Configuration for pair $pair changed. Stopping process for restart.");
                    $this->stopProcess($pair);
                } else {
                    $this->logger->info("!!!!! Process for pair $pair not running. Starting new process.");
                }
                
                // Start process with new configuration
                $this->startProcess($pair);
                
                // Save new configuration hash
                $this->pairConfigHashes[$pair] = $configHash;
                $this->logger->info("!!!!! Updated configuration hash for pair $pair");
            } else {
                $this->logger->info("!!!!! Configuration for pair $pair did not change. Process is running, no restart needed.");
            }
        }
        
        // Check for processes that are no longer active or configured
        foreach ($runningProcesses as $runningPair) {
            if (!in_array($runningPair, $pairs)) {
                $this->logger->info("!!!!! Pair $runningPair is no longer active. Stopping process.");
                $this->stopProcess($runningPair);
                
                // Remove configuration hash for inactive pair
                if (isset($this->pairConfigHashes[$runningPair])) {
                    unset($this->pairConfigHashes[$runningPair]);
                    $this->logger->info("!!!!! Removed configuration hash for inactive pair $runningPair");
                }
            }
        }
        
        $this->logger->info("!!!!! Updating processes completed");
    }
    
    /**
     * Cleaning invalid PID files
     */
    public function cleanupInvalidPidFiles(): void
    {
        $this->logger->log("Cleaning invalid PID files...");
        $pidFiles = glob("{$this->pidDir}/*.pid");
        
        if (empty($pidFiles)) {
            $this->logger->log("PID files not found");
        } else {
            $this->logger->log("Found PID files: " . implode(", ", $pidFiles));
        }
        
        foreach ($pidFiles as $pidFile) {
            $pair = basename($pidFile, '.pid');
            $pid = (int)file_get_contents($pidFile);
            $this->logger->log("Checking PID {$pid} for pair {$pair}");
            
            if ($pid <= 0) {
                $this->logger->log("Removing invalid PID file for pair {$pair} (invalid PID: {$pid})");
                unlink($pidFile);
                continue;
            }
            
            // Skip new files (less than 10 seconds old)
            $fileCreationTime = filemtime($pidFile);
            if ((time() - $fileCreationTime) < 10) {
                $this->logger->log("PID file for pair {$pair} with PID {$pid} created recently, skipping");
                continue;
            }
            
            // Check via posix_kill and ps
            if (!posix_kill($pid, 0)) {
                exec("ps -p {$pid} -o pid=", $output);
                if (empty($output)) {
                    $this->logger->log("Removing PID file for non-existing process {$pid} for pair {$pair}");
                    unlink($pidFile);
                } else {
                    $this->logger->log("Process {$pid} for pair {$pair} is still alive (ps)");
                }
            } else {
                $this->logger->log("Process {$pid} for pair {$pair} is alive (posix_kill)");
            }
        }
        
        $this->logger->log("Cleaning invalid PID files completed");
    }
    
    /**
     * Find running BotRunner processes for a specific pair
     */
    private function findRunningBotRunnerProcesses(string $pair): array
    {
        $this->logger->log("Finding running BotRunner processes for pair {$pair}");
        $result = [];
        
        // Search for all PHP processes
        $processes = glob('/proc/*/cmdline');
        
        foreach ($processes as $procFile) {
            if (file_exists($procFile)) {
                $cmdline = file_get_contents($procFile);
                
                if (strpos($cmdline, 'BotRunner') !== false && strpos($cmdline, $pair) !== false) {
                    $pidDir = dirname($procFile);
                    $pid = basename($pidDir);
                    $result[] = (int)$pid;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Find orphaned BotRunner processes (running but no corresponding PID file)
     */
    private function findOrphanedBotRunnerProcesses(): array
    {
        $this->logger->log("Finding 'orphaned' BotRunner processes");
        $result = [];
        
        // Get a list of all PIDs from PID files
        $pidFiles = glob($this->pidDir . '/*.pid');
        $knownPids = [];
        
        foreach ($pidFiles as $pidFile) {
            if (file_exists($pidFile)) {
                $knownPids[] = (int)file_get_contents($pidFile);
            }
        }
        
        // Search for all PHP processes
        $processes = glob('/proc/*/cmdline');
        
        foreach ($processes as $procFile) {
            if (file_exists($procFile)) {
                $cmdline = file_get_contents($procFile);
                
                if (strpos($cmdline, 'BotRunner') !== false) {
                    $pidDir = dirname($procFile);
                    $pid = (int)basename($pidDir);
                    
                    if (!in_array($pid, $knownPids)) {
                        $result[] = $pid;
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Count running processes for a pair
     */
    private function countRunningProcessesForPair(string $pair): int
    {
        $this->logger->log("Counting running processes for pair {$pair}");
        
        // Use method to find processes via /proc
        $processes = $this->findRunningBotRunnerProcesses($pair);
        $count = count($processes);
        
        $this->logger->log("Found {$count} running processes for pair {$pair}");
        return $count;
    }
    
    /**
     * Kill all processes for a pair
     */
    private function killAllProcessesForPair(string $pair): void
    {
        $this->logger->log("Stopping all processes for pair {$pair}");
        
        $processes = $this->findRunningBotRunnerProcesses($pair);
        
        if (empty($processes)) {
            $this->logger->log("No running processes found for pair {$pair}");
            return;
        }
        
        $this->logger->log("Found processes for stopping: " . implode(", ", $processes));
        
        foreach ($processes as $pid) {
            $this->logger->log("Stopping process {$pid}");
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGTERM);
            } else {
                exec("kill -15 {$pid}");
            }
        }
        
        // Check if processes are stopped
        sleep(1);
        $remaining = $this->findRunningBotRunnerProcesses($pair);
        
        if (!empty($remaining)) {
            $this->logger->log("Processes still running after SIGTERM: " . implode(", ", $remaining));
            $this->logger->log("Applying SIGKILL to remaining processes");
            
            foreach ($remaining as $pid) {
                if (function_exists('posix_kill')) {
                    posix_kill($pid, SIGKILL);
                } else {
                    exec("kill -9 {$pid}");
                }
            }
        }
        
        $this->logger->log("All processes for pair {$pair} stopped");
    }
    
    /**
     * Get a list of all running processes (pairs)
     *
     * @return array Array of pair names for which processes are running
     */
    public function getRunningProcesses(): array
    {
        $this->logger->info("!!!!! Getting list of running processes");
        $runningProcesses = [];
        
        $pidFiles = glob($this->pidDir . '/*.pid');
        $this->logger->info("!!!!! Found PID files: " . implode(", ", $pidFiles ?: []));
        
        foreach ($pidFiles as $pidFile) {
            $pairName = basename($pidFile, '.pid');
            
            try {
                $pair = $this->restorePairFormat($pairName);
                
                if ($this->isProcessRunning($pair)) {
                    $runningProcesses[] = $pair;
                } else {
                    // If the PID file exists, but the process is not running, remove the file
                    $this->logger->info("!!!!! PID file for pair $pair exists, but process not running. Removing file.");
                    unlink($pidFile);
                }
            } catch (Exception $e) {
                $this->logger->error("!!!!! Error processing PID file for pair $pairName: " . $e->getMessage());
                // Remove problematic PID file
                if (file_exists($pidFile)) {
                    $this->logger->info("!!!!! Removing problematic PID file $pidFile");
                    unlink($pidFile);
                }
            }
        }
        
        $this->logger->info("!!!!! Running processes: " . implode(", ", $runningProcesses ?: []));
        return $runningProcesses;
    }
} 