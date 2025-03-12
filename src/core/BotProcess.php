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
        
        $this->logger->log("Запуск процесу для пари {$pair}");
        
        // Check if the process is already running for this pair
        if ($this->isProcessRunning($pair)) {
            $this->logger->log("Процес для пари {$pair} вже запущений");
            return true;
        }
        
        // Додаткова перевірка запущених процесів для цієї пари
        // Використовуємо більш надійний спосіб перевірки
        $pidFile = $this->getPidFilePath($this->formatPairForFileName($pair));
        $this->logger->log("Перевірка PID файлу: {$pidFile}");
        
        if (file_exists($pidFile)) {
            $pid = (int)file_get_contents($pidFile);
            $this->logger->log("Знайдено існуючий PID файл з PID: {$pid}");
            
            if ($pid > 0 && file_exists("/proc/{$pid}")) {
                $this->logger->log("Процес {$pid} існує, зупиняємо його перед запуском нового");
                $this->stopProcess($pair);
                $this->logger->log("Очікуємо 1 секунду після зупинки процесу");
                sleep(1);
            } else {
                $this->logger->log("PID файл існує, але процес {$pid} не запущений, видаляємо файл");
                unlink($pidFile);
            }
        }
        
        // Перевірка на запущені процеси BotRunner для цієї пари через proc
        $this->logger->log("Перевірка на запущені процеси BotRunner для пари {$pair}");
        $runningProcesses = $this->findRunningBotRunnerProcesses($pair);
        
        if (!empty($runningProcesses)) {
            $this->logger->log("Знайдено запущені процеси BotRunner для пари {$pair}: " . implode(", ", $runningProcesses));
            foreach ($runningProcesses as $pid) {
                $this->logger->log("Зупиняємо процес {$pid}");
                if (function_exists('posix_kill')) {
                    posix_kill($pid, SIGTERM);
                } else {
                    exec("kill -15 {$pid}");
                }
            }
            $this->logger->log("Очікуємо 1 секунду після зупинки процесів");
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
     * @return bool Whether the process was stopped successfully
     */
    public function stopProcess(string $pair): bool
    {
        $this->logger->log("Зупинка процесу для пари {$pair}");
        
        // Format the pair for the PID file name
        $safePair = $this->formatPairForFileName($pair);
        
        // Path to the PID file
        $pidFile = $this->getPidFilePath($safePair);
        
        $this->logger->log("Шлях до PID файлу: {$pidFile}");
        
        // If the PID file does not exist, the process is not running
        if (!file_exists($pidFile)) {
            $this->logger->log("PID файл не існує для пари {$pair}, процес не запущений");
            return true;
        }
        
        // Reading the PID from the file
        $pid = (int)file_get_contents($pidFile);
        
        if ($pid <= 0) {
            $this->logger->log("Невірний PID ({$pid}) у файлі для пари {$pair}");
            unlink($pidFile);
            return true;
        }
        
        $this->logger->log("Знайдено PID: {$pid} для пари {$pair}");
        
        // Trying to stop the process gracefully first (SIGTERM)
        $this->logger->log("Надсилаємо сигнал SIGTERM до процесу {$pid}");
        $sigResult = false;
        if (function_exists('posix_kill')) {
            $sigResult = posix_kill($pid, SIGTERM);
            $this->logger->log("Результат posix_kill: " . ($sigResult ? "успішно" : "невдало"));
        } else {
            // Fallback для систем без posix_kill (Docker)
            $this->logger->log("posix_kill не доступний, використовуємо kill -15");
            exec("kill -15 {$pid}", $output, $retval);
            $sigResult = ($retval === 0);
            $this->logger->log("Результат kill -15: " . ($sigResult ? "успішно" : "невдало") . ", код: {$retval}");
        }
        
        // Waiting for the process to exit
        $this->logger->log("Очікуємо завершення процесу {$pid}...");
        $maxWait = 5; // максимальний час очікування у секундах
        $waited = 0;
        while ($waited < $maxWait) {
            if (!file_exists("/proc/{$pid}")) {
                $this->logger->log("Процес {$pid} завершено за {$waited} секунд");
                break;
            }
            sleep(1);
            $waited++;
        }
        
        // If the process is still running, forcefully terminate it (SIGKILL)
        if (file_exists("/proc/{$pid}")) {
            $this->logger->log("Процес {$pid} все ще працює після {$waited} секунд, надсилаємо SIGKILL");
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGKILL);
            } else {
                exec("kill -9 {$pid}");
            }
            $this->logger->log("Надіслано SIGKILL до процесу {$pid}");
            sleep(1);
        }
        
        // Removing the PID file
        if (file_exists($pidFile)) {
            $this->logger->log("Видаляємо PID файл {$pidFile}");
            unlink($pidFile);
        }
        
        // Verifying that the process is no longer running
        if (file_exists("/proc/{$pid}")) {
            $this->logger->log("УВАГА: Процес {$pid} все ще працює після всіх спроб зупинки");
            return false;
        }
        
        $this->logger->log("Процес для пари {$pair} успішно зупинено");
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
        $this->logger->log("Перевірка чи запущений процес для пари {$pair}");
        
        // Format the pair for the PID file name
        $safePair = $this->formatPairForFileName($pair);
        
        // Path to the PID file
        $pidFile = $this->getPidFilePath($safePair);
        
        $this->logger->log("Шлях до PID файлу: {$pidFile}");
        
        // If the PID file does not exist, the process is not running
        if (!file_exists($pidFile)) {
            $this->logger->log("PID файл не існує для пари {$pair}");
            return false;
        }
        
        // Reading the PID from the file
        $pid = (int)file_get_contents($pidFile);
        
        if ($pid <= 0) {
            $this->logger->log("Невірний PID ({$pid}) у файлі для пари {$pair}");
            // Видаляємо невірний PID файл
            unlink($pidFile);
            return false;
        }
        
        $this->logger->log("Знайдено PID: {$pid} для пари {$pair}");
        
        // Checking if the process is running using proc filesystem (works in Docker)
        if (file_exists("/proc/{$pid}")) {
            // Додаткова перевірка, чи це процес BotRunner
            $this->logger->log("Процес з PID {$pid} існує, перевіряємо чи це BotRunner");
            $cmdlineFile = "/proc/{$pid}/cmdline";
            
            if (file_exists($cmdlineFile)) {
                $cmdline = file_get_contents($cmdlineFile);
                if (strpos($cmdline, 'BotRunner') !== false && strpos($cmdline, $pair) !== false) {
                    $this->logger->log("Підтверджено: процес з PID {$pid} є BotRunner для пари {$pair}");
                    return true;
                } else {
                    $this->logger->log("Процес з PID {$pid} існує, але це не BotRunner для пари {$pair}");
                    $this->logger->log("Командний рядок: " . $cmdline);
                }
            } else {
                $this->logger->log("Не вдалося прочитати cmdline для процесу {$pid}");
            }
        } else {
            $this->logger->log("Процес з PID {$pid} не існує");
        }
        
        // Якщо ми дійшли сюди, то PID файл існує, але процес не запущений або не є BotRunner
        $this->logger->log("Видаляємо невірний PID файл для пари {$pair}");
        unlink($pidFile);
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
        $this->logger->log("Cleaned invalid PID files");
        // Reloading the configuration
        Config::reloadConfig();
        $this->logger->log("Reloaded the configuration");
        
        // Getting the list of active pairs
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("Enabled pairs: " . implode(", ", $enabledPairs));
        // Stopping processes for inactive pairs
        $pidFiles = glob($this->pidDir . '/*.pid');
        $this->logger->log("PID files: " . implode(", ", $pidFiles));
        foreach ($pidFiles as $pidFile) {
            $pairName = basename($pidFile, '.pid');
            $pair = $this->restorePairFormat($pairName);
            $this->logger->log("Pair: " . $pair);
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
        $this->logger->log("Очищення невірних PID файлів...");
        
        // Getting the list of PID files
        $pidFiles = glob($this->pidDir . '/*.pid');
        
        if (empty($pidFiles)) {
            $this->logger->log("PID файли не знайдено");
        } else {
            $this->logger->log("Знайдено PID файли: " . implode(", ", $pidFiles));
        }
        
        foreach ($pidFiles as $pidFile) {
            $pairName = basename($pidFile, '.pid');
            $pair = $this->restorePairFormat($pairName);
            
            $this->logger->log("Перевірка PID файлу: {$pidFile} для пари {$pair}");
            
            if (file_exists($pidFile)) {
                $pid = (int)file_get_contents($pidFile);
                
                if ($pid <= 0) {
                    $this->logger->log("Видалення невірного PID файлу для пари {$pair} (некоректний PID: {$pid})");
                    unlink($pidFile);
                    continue;
                }
                
                // Перевірка існування процесу через /proc (працює в Docker)
                if (!file_exists("/proc/{$pid}")) {
                    $this->logger->log("Видалення PID файлу для неіснуючого процесу {$pid} для пари {$pair}");
                    unlink($pidFile);
                    continue;
                }
                
                // Перевіряємо, чи це дійсно наш процес BotRunner
                $cmdlineFile = "/proc/{$pid}/cmdline";
                
                if (file_exists($cmdlineFile)) {
                    $cmdline = file_get_contents($cmdlineFile);
                    if (strpos($cmdline, 'BotRunner') === false) {
                        $this->logger->log("Процес {$pid} існує, але це не BotRunner процес, видаляємо PID файл для пари {$pair}");
                        $this->logger->log("Командний рядок: " . $cmdline);
                        unlink($pidFile);
                    } else {
                        $this->logger->log("Процес {$pid} підтверджено як BotRunner для пари {$pair}");
                    }
                } else {
                    $this->logger->log("Не вдалося прочитати cmdline для процесу {$pid}, ймовірно процес вже завершився");
                    unlink($pidFile);
                }
            }
        }
        
        // Перевірка на "осиротілі" процеси BotRunner, які працюють без PID-файлів
        $this->logger->log("Перевірка на 'осиротілі' процеси BotRunner...");
        $orphanedProcesses = $this->findOrphanedBotRunnerProcesses();
        
        if (empty($orphanedProcesses)) {
            $this->logger->log("'Осиротілі' процеси BotRunner не знайдено");
        } else {
            $this->logger->log("Знайдено 'осиротілі' процеси BotRunner: " . implode(", ", $orphanedProcesses));
            
            foreach ($orphanedProcesses as $pid) {
                $this->logger->log("Зупиняємо 'осиротілий' процес BotRunner з PID {$pid}");
                if (function_exists('posix_kill')) {
                    posix_kill($pid, SIGTERM);
                } else {
                    exec("kill -15 {$pid}");
                }
            }
        }
        
        $this->logger->log("Очищення невірних PID файлів завершено");
    }
    
    /**
     * Знаходить запущені процеси BotRunner для вказаної пари
     */
    private function findRunningBotRunnerProcesses(string $pair): array
    {
        $this->logger->log("Пошук запущених процесів BotRunner для пари {$pair}");
        $result = [];
        
        // Шукаємо всі процеси PHP
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
     * Знаходить "осиротілі" процеси BotRunner без відповідних PID-файлів
     */
    private function findOrphanedBotRunnerProcesses(): array
    {
        $this->logger->log("Пошук 'осиротілих' процесів BotRunner");
        $result = [];
        
        // Отримуємо список всіх PID з PID-файлів
        $pidFiles = glob($this->pidDir . '/*.pid');
        $knownPids = [];
        
        foreach ($pidFiles as $pidFile) {
            if (file_exists($pidFile)) {
                $knownPids[] = (int)file_get_contents($pidFile);
            }
        }
        
        // Шукаємо всі процеси PHP
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
     * Підрахунок кількості запущених процесів для пари
     */
    private function countRunningProcessesForPair(string $pair): int
    {
        $this->logger->log("Підрахунок запущених процесів для пари {$pair}");
        
        // Використовуємо метод пошуку процесів через /proc
        $processes = $this->findRunningBotRunnerProcesses($pair);
        $count = count($processes);
        
        $this->logger->log("Знайдено {$count} запущених процесів для пари {$pair}");
        return $count;
    }
    
    /**
     * Зупинка всіх процесів для пари
     */
    private function killAllProcessesForPair(string $pair): void
    {
        $this->logger->log("Зупинка всіх процесів для пари {$pair}");
        
        $processes = $this->findRunningBotRunnerProcesses($pair);
        
        if (empty($processes)) {
            $this->logger->log("Не знайдено запущених процесів для пари {$pair}");
            return;
        }
        
        $this->logger->log("Знайдено процеси для зупинки: " . implode(", ", $processes));
        
        foreach ($processes as $pid) {
            $this->logger->log("Зупиняємо процес {$pid}");
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGTERM);
            } else {
                exec("kill -15 {$pid}");
            }
        }
        
        // Перевіряємо, чи процеси зупинені
        sleep(1);
        $remaining = $this->findRunningBotRunnerProcesses($pair);
        
        if (!empty($remaining)) {
            $this->logger->log("Процеси все ще працюють після SIGTERM: " . implode(", ", $remaining));
            $this->logger->log("Застосовуємо SIGKILL для залишкових процесів");
            
            foreach ($remaining as $pid) {
                if (function_exists('posix_kill')) {
                    posix_kill($pid, SIGKILL);
                } else {
                    exec("kill -9 {$pid}");
                }
            }
        }
        
        $this->logger->log("Всі процеси для пари {$pair} зупинено");
    }
} 