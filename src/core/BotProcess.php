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
    /** @var array Хеші конфігурацій для кожної пари */
    private $configHashes = [];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Використовуємо шлях до звичайного файлу логів
        $environment = getenv('ENVIRONMENT') ?: 'local';
        $logFile = __DIR__ . '/../../data/logs/' . $environment . '/bot.log';
        
        $this->logger = Logger::getInstance(true, $logFile);
        $this->pidDir = __DIR__ . '/../../data/pids';
        $this->logger->log("BotProcess: Ініціалізація, шлях до PID-директорії: {$this->pidDir}");
        
        // Create the directory for PID files if it doesn't exist
        if (!is_dir($this->pidDir)) {
            mkdir($this->pidDir, 0755, true);
            $this->logger->log("BotProcess: Створено директорію для PID-файлів: {$this->pidDir}");
        } else {
            $this->logger->log("BotProcess: Директорія для PID-файлів існує");
        }
        
        // Створюємо директорію для лок-файлів
        $lockDir = __DIR__ . '/../../data/locks';
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
            $this->logger->log("BotProcess: Створено директорію для лок-файлів: {$lockDir}");
        } else {
            $this->logger->log("BotProcess: Директорія для лок-файлів існує");
        }
        
        // Ініціалізуємо хеші конфігурацій для всіх пар
        $this->initPairConfigHashes();
    }
    
    /**
     * Ініціалізація хешів конфігурацій для всіх пар
     */
    private function initPairConfigHashes(): void
    {
        $this->logger->log("!!!!! BotProcess::initPairConfigHashes(): Ініціалізація хешів конфігурацій");
        
        // Отримуємо всі пари з конфігурації
        $allPairs = Config::getAllPairs();
        
        foreach ($allPairs as $pair) {
            $pairConfig = Config::getPairConfig($pair);
            if ($pairConfig) {
                $this->pairConfigHashes[$pair] = md5(json_encode($pairConfig));
                $this->logger->log("!!!!! BotProcess::initPairConfigHashes(): Ініціалізовано хеш для пари {$pair}");
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
        // Перевірка на порожню пару
        if (empty($pair)) {
            $this->logger->error("Cannot start process for empty pair");
            return false;
        }
        
        $this->logger->log("!!!!! BotProcess::startProcess(): Запуск процесу для пари {$pair}");
        
        // Check if the process is already running for this pair
        if ($this->isProcessRunning($pair)) {
            $this->logger->log("!!!!! BotProcess::startProcess(): Процес для пари {$pair} вже запущений, пропускаємо запуск");
            return true;
        }
        
        // Додаткова перевірка запущених процесів для цієї пари
        // Використовуємо більш надійний спосіб перевірки
        $pidFile = $this->getPidFilePath($this->formatPairForFileName($pair));
        $this->logger->log("!!!!! BotProcess::startProcess(): Перевірка PID файлу для пари {$pair}: {$pidFile}");
        
        if (file_exists($pidFile)) {
            $pid = (int)file_get_contents($pidFile);
            $this->logger->log("!!!!! BotProcess::startProcess(): Знайдено існуючий PID файл для пари {$pair} з PID: {$pid}");
            
            if ($pid > 0 && file_exists("/proc/{$pid}")) {
                $this->logger->log("!!!!! BotProcess::startProcess(): Процес {$pid} для пари {$pair} існує, зупиняємо його перед запуском нового");
                $this->stopProcess($pair);
                $this->logger->log("!!!!! BotProcess::startProcess(): Очікуємо 1 секунду після зупинки процесу для пари {$pair}");
                sleep(1);
            } else {
                $this->logger->log("!!!!! BotProcess::startProcess(): PID файл для пари {$pair} існує, але процес {$pid} не запущений, видаляємо файл");
                unlink($pidFile);
            }
        } else {
            $this->logger->log("!!!!! BotProcess::startProcess(): PID файл для пари {$pair} не існує");
        }
        
        // Перевірка на запущені процеси BotRunner для цієї пари через proc
        $this->logger->log("!!!!! BotProcess::startProcess(): Перевірка на запущені процеси BotRunner для пари {$pair}");
        $runningProcesses = $this->findRunningBotRunnerProcesses($pair);
        
        if (!empty($runningProcesses)) {
            $this->logger->log("!!!!! BotProcess::startProcess(): Знайдено запущені процеси BotRunner для пари {$pair}: " . implode(", ", $runningProcesses));
            foreach ($runningProcesses as $pid) {
                $this->logger->log("!!!!! BotProcess::startProcess(): Зупиняємо процес {$pid} для пари {$pair}");
                if (function_exists('posix_kill')) {
                    $killResult = posix_kill($pid, SIGTERM);
                    $this->logger->log("!!!!! BotProcess::startProcess(): Результат posix_kill для PID {$pid} пари {$pair}: " . ($killResult ? "успішно" : "невдало"));
                } else {
                    exec("kill -15 {$pid}");
                    $this->logger->log("!!!!! BotProcess::startProcess(): Виконано команду kill -15 для PID {$pid} пари {$pair}");
                }
            }
            $this->logger->log("!!!!! BotProcess::startProcess(): Очікуємо 1 секунду після зупинки процесів для пари {$pair}");
            sleep(1);
        } else {
            $this->logger->log("!!!!! BotProcess::startProcess(): Не знайдено запущених процесів BotRunner для пари {$pair}");
        }
        
        // Path to the script that will be executed in a separate process
        $scriptPath = __DIR__ . '/TradingBotRunner.php';
        
        // Format the pair for the PID file name
        $safePair = $this->formatPairForFileName($pair);
        
        // Acquire a lock to ensure no parallel process starts for this pair
        $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
        $this->logger->log("!!!!! BotProcess::startProcess(): Спроба блокування для пари {$pair}, лок-файл: {$lockFile}");
        
        // Перевіряємо, чи існує вже лок-файл і чи він заблокований
        if (file_exists($lockFile)) {
            $existingLockHandle = @fopen($lockFile, 'r');
            if ($existingLockHandle) {
                $locked = !flock($existingLockHandle, LOCK_EX | LOCK_NB);
                fclose($existingLockHandle);
                
                if ($locked) {
                    $this->logger->log("!!!!! BotProcess::startProcess(): Лок-файл для пари {$pair} вже заблокований. Інший процес вже запущений або запускається.");
                    return false;
                } else {
                    $this->logger->log("!!!!! BotProcess::startProcess(): Лок-файл для пари {$pair} існує, але не заблокований. Видаляємо його.");
                    unlink($lockFile);
                }
            }
        }
        
        $lockHandle = fopen($lockFile, 'c');
        
        if (!$lockHandle) {
            $this->logger->error("!!!!! BotProcess::startProcess(): Не вдалося створити лок-файл для пари {$pair}");
            return false;
        }
        
        $locked = flock($lockHandle, LOCK_EX | LOCK_NB);
        $this->logger->log("!!!!! BotProcess::startProcess(): Статус блокування для пари {$pair}: " . ($locked ? "успішно" : "заблоковано"));
        
        if (!$locked) {
            $this->logger->log("!!!!! BotProcess::startProcess(): Не вдалося отримати блокування для пари {$pair}, можливо інший процес вже запускається");
            fclose($lockHandle);
            return false;
        }
        
        // Зберігаємо хендл лок-файлу в додатковому файлі для подальшого використання
        $lockHandleFile = $this->pidDir . "/{$safePair}_lock_handle.txt";
        file_put_contents($lockHandleFile, "locked");
        
        try {
            // Використовуємо стандартне перенаправлення в /dev/null
            // для запуску процесу у фоновому режимі
            $command = sprintf(
                'php %s %s > /dev/null 2>&1 & echo $!',
                escapeshellarg($scriptPath),
                escapeshellarg($pair)
            );
            
            $this->logger->log("!!!!! BotProcess::startProcess(): Виконуємо команду для запуску процесу для пари {$pair}: {$command}");
            
            // Execute the command and get the PID of the process
            $pid = exec($command);
            
            if (!$pid || !is_numeric($pid) || $pid <= 0) {
                $this->logger->error("!!!!! BotProcess::startProcess(): Помилка при запуску процесу для пари {$pair}, неправильний PID: {$pid}");
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                return false;
            }
            
            $this->logger->log("!!!!! BotProcess::startProcess(): Процес запущено для пари {$pair}, PID: {$pid}");
            
            // Saving the PID to a file
            $this->savePid($safePair, (int)$pid);
            
            // Releasing the lock
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            
            // Checking if the process is actually running
            usleep(500000); // 0.5 second
            if (file_exists("/proc/{$pid}")) {
                $this->logger->log("!!!!! BotProcess::startProcess(): Перевірено, що процес {$pid} для пари {$pair} успішно запущений");
                return true;
            } else {
                $this->logger->error("!!!!! BotProcess::startProcess(): Процес {$pid} для пари {$pair} не запустився або швидко завершився");
                
                $this->removePidFile($safePair);
                return false;
            }
            
        } catch (\Throwable $e) {
            $this->logger->error("!!!!! BotProcess::startProcess(): Виняток при запуску процесу для пари {$pair}: " . $e->getMessage());
            $this->logger->error("!!!!! BotProcess::startProcess(): Стек-трейс: " . $e->getTraceAsString());
            
            // Releasing the lock
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            
            return false;
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
        $this->logger->log("!!!!! BotProcess::stopProcess(): Зупинка процесу для пари {$pair}");
        
        // Format the pair for the PID file name
        $safePair = $this->formatPairForFileName($pair);
        
        // Path to the PID file
        $pidFile = $this->getPidFilePath($safePair);
        
        $this->logger->log("!!!!! BotProcess::stopProcess(): Шлях до PID файлу для пари {$pair}: {$pidFile}");
        
        // If the PID file does not exist, the process is not running
        if (!file_exists($pidFile)) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): PID файл не існує для пари {$pair}, процес не запущений");
            
            // Перевіряємо, чи існує лок-файл і видаляємо його
            $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
            if (file_exists($lockFile)) {
                $this->logger->log("!!!!! BotProcess::stopProcess(): Видаляємо лок-файл для пари {$pair}: {$lockFile}");
                @unlink($lockFile);
            }
            
            return true;
        }
        
        // Reading the PID from the file
        $pid = (int)file_get_contents($pidFile);
        
        if ($pid <= 0) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): Невірний PID ({$pid}) у файлі для пари {$pair}");
            unlink($pidFile);
            
            // Видаляємо лок-файл
            $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
            if (file_exists($lockFile)) {
                $this->logger->log("!!!!! BotProcess::stopProcess(): Видаляємо лок-файл для пари {$pair}: {$lockFile}");
                @unlink($lockFile);
            }
            
            return true;
        }
        
        $this->logger->log("!!!!! BotProcess::stopProcess(): Знайдено PID: {$pid} для зупинки пари {$pair}");
        
        // Trying to stop the process gracefully first (SIGTERM)
        $this->logger->log("!!!!! BotProcess::stopProcess(): Надсилаємо сигнал SIGTERM до процесу {$pid} для пари {$pair}");
        $sigResult = false;
        if (function_exists('posix_kill')) {
            $sigResult = posix_kill($pid, SIGTERM);
            $this->logger->log("!!!!! BotProcess::stopProcess(): Результат posix_kill для пари {$pair}: " . ($sigResult ? "успішно" : "невдало"));
        } else {
            // Fallback для систем без posix_kill (Docker)
            $this->logger->log("!!!!! BotProcess::stopProcess(): posix_kill не доступний для пари {$pair}, використовуємо kill -15");
            exec("kill -15 {$pid}", $output, $retval);
            $sigResult = ($retval === 0);
            $this->logger->log("!!!!! BotProcess::stopProcess(): Результат kill -15 для пари {$pair}: " . ($sigResult ? "успішно" : "невдало") . ", код: {$retval}");
        }
        
        // Waiting for the process to exit
        $this->logger->log("!!!!! BotProcess::stopProcess(): Очікуємо завершення процесу {$pid} для пари {$pair}...");
        $maxWait = 5; // максимальний час очікування у секундах
        $waited = 0;
        while ($waited < $maxWait) {
            if (!file_exists("/proc/{$pid}")) {
                $this->logger->log("!!!!! BotProcess::stopProcess(): Процес {$pid} для пари {$pair} завершено за {$waited} секунд");
                break;
            }
            sleep(1);
            $waited++;
        }
        
        // If the process is still running, forcefully terminate it (SIGKILL)
        if (file_exists("/proc/{$pid}")) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): Процес {$pid} для пари {$pair} все ще працює після {$waited} секунд, надсилаємо SIGKILL");
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGKILL);
            } else {
                exec("kill -9 {$pid}");
            }
            $this->logger->log("!!!!! BotProcess::stopProcess(): Надіслано SIGKILL до процесу {$pid} для пари {$pair}");
            sleep(1);
        }
        
        // Removing the PID file
        if (file_exists($pidFile)) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): Видаляємо PID файл {$pidFile} для пари {$pair}");
            unlink($pidFile);
        }
        
        // Видаляємо лок-файл
        $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
        if (file_exists($lockFile)) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): Видаляємо лок-файл для пари {$pair}: {$lockFile}");
            @unlink($lockFile);
        }
        
        // Видаляємо файл хендлера лок-файлу
        $lockHandleFile = $this->pidDir . "/{$safePair}_lock_handle.txt";
        if (file_exists($lockHandleFile)) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): Видаляємо файл хендлера лок-файлу для пари {$pair}: {$lockHandleFile}");
            @unlink($lockHandleFile);
        }
        
        // Verifying that the process is no longer running
        if (file_exists("/proc/{$pid}")) {
            $this->logger->log("!!!!! BotProcess::stopProcess(): УВАГА: Процес {$pid} для пари {$pair} все ще працює після всіх спроб зупинки");
            return false;
        }
        
        $this->logger->log("!!!!! BotProcess::stopProcess(): Процес для пари {$pair} успішно зупинено");
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
        $this->logger->log("!!!!! BotProcess::isProcessRunning(): Перевірка чи запущений процес для пари {$pair}");
        
        // Format the pair for the PID file name
        $safePair = $this->formatPairForFileName($pair);
        
        // Path to the PID file
        $pidFile = $this->getPidFilePath($safePair);
        
        $this->logger->log("!!!!! BotProcess::isProcessRunning(): Шлях до PID файлу: {$pidFile}");
        
        // Перевіряємо, чи існує лок-файл для цієї пари
        $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
        if (file_exists($lockFile)) {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Знайдено лок-файл для пари {$pair}: {$lockFile}");
            
            // Перевіряємо, чи лок-файл заблокований
            $lockHandle = @fopen($lockFile, 'r');
            if ($lockHandle) {
                $lockStatus = !flock($lockHandle, LOCK_EX | LOCK_NB);
                fclose($lockHandle);
                
                if ($lockStatus) {
                    $this->logger->log("!!!!! BotProcess::isProcessRunning(): Лок-файл для пари {$pair} заблокований, процес вважається запущеним");
                    
                    // Якщо PID-файл існує, але лок-файл заблокований, це добре
                    // Якщо PID-файл не існує, але лок-файл заблокований, щось не так
                    if (!file_exists($pidFile)) {
                        $this->logger->log("!!!!! BotProcess::isProcessRunning(): УВАГА: Лок-файл для пари {$pair} заблокований, але PID-файл не існує");
                        // Спробуємо відновити PID-файл з лок-файлу
                        $pid = file_get_contents($lockFile);
                        if (is_numeric($pid) && $pid > 0) {
                            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Відновлюємо PID-файл для пари {$pair} з лок-файлу, PID: {$pid}");
                            $this->savePid($safePair, (int) $pid);
                        }
                    }
                    
                    return true;
                } else {
                    $this->logger->log("!!!!! BotProcess::isProcessRunning(): Лок-файл для пари {$pair} не заблокований, перевіряємо PID-файл далі");
                }
            }
        } else {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Лок-файл для пари {$pair} не знайдено");
        }
        
        // If the PID file does not exist, the process is not running
        if (!file_exists($pidFile)) {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): PID файл не існує для пари {$pair}");
            return false;
        }
        
        // Reading the PID from the file
        $pid = (int)file_get_contents($pidFile);
        
        if ($pid <= 0) {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Невірний PID ({$pid}) у файлі для пари {$pair}");
            // Видаляємо невірний PID файл
            unlink($pidFile);
            return false;
        }
        
        $this->logger->log("!!!!! BotProcess::isProcessRunning(): Знайдено PID: {$pid} для пари {$pair}");
        
        // Checking if the process is running using proc filesystem (works in Docker)
        if (file_exists("/proc/{$pid}")) {
            // Додаткова перевірка, чи це процес BotRunner
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Процес з PID {$pid} існує, перевіряємо чи це BotRunner");
            $cmdlineFile = "/proc/{$pid}/cmdline";
            
            if (file_exists($cmdlineFile)) {
                $cmdline = file_get_contents($cmdlineFile);
                if (strpos($cmdline, 'BotRunner') !== false && strpos($cmdline, $pair) !== false) {
                    $this->logger->log("!!!!! BotProcess::isProcessRunning(): Підтверджено: процес з PID {$pid} є BotRunner для пари {$pair}");
                    
                    // Якщо процес існує, але лок-файл не існує або не заблокований,
                    // спробуємо відновити лок-файл
                    if (!file_exists($lockFile)) {
                        $this->logger->log("!!!!! BotProcess::isProcessRunning(): УВАГА: Процес для пари {$pair} існує, але лок-файл не знайдено. Створюємо новий лок-файл.");
                        file_put_contents($lockFile, $pid);
                    }
                    
                    return true;
                } else {
                    $this->logger->log("!!!!! BotProcess::isProcessRunning(): Процес з PID {$pid} існує, але це не BotRunner для пари {$pair}");
                    $this->logger->log("!!!!! BotProcess::isProcessRunning(): Командний рядок: " . $cmdline);
                }
            } else {
                $this->logger->log("!!!!! BotProcess::isProcessRunning(): Не вдалося прочитати cmdline для процесу {$pid}");
            }
        } else {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Процес з PID {$pid} не існує");
        }
        
        // Якщо ми дійшли сюди, то PID файл існує, але процес не запущений або не є BotRunner
        $this->logger->log("!!!!! BotProcess::isProcessRunning(): Видаляємо невірний PID файл для пари {$pair}");
        unlink($pidFile);
        
        // Також видаляємо лок-файл, якщо він існує
        if (file_exists($lockFile)) {
            $this->logger->log("!!!!! BotProcess::isProcessRunning(): Видаляємо лок-файл для пари {$pair}");
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
        $this->logger->log("Зупинка всіх процесів ботів...");
        $files = glob("{$this->pidDir}/*.pid");
        
        $this->logger->log("Знайдено PID-файли для зупинки: " . implode(", ", $files));
        
        foreach ($files as $file) {
            $pairName = basename($file, '.pid');
            $pair = $this->restorePairFormat($pairName);
            $this->logger->log("Зупинка процесу для пари {$pair}, PID-файл: {$file}");
            $pid = (int)file_get_contents($file);
            $this->logger->log("PID для зупинки: {$pid}, пара: {$pair}");
            
            if (posix_kill($pid, SIGTERM)) {
                $this->logger->log("Надіслано сигнал SIGTERM до процесу {$pid} для пари {$pair}");
                $startTime = time();
                while (posix_kill($pid, 0) && (time() - $startTime) < 5) {
                    usleep(100000);
                }
                if (!posix_kill($pid, 0)) {
                    $this->logger->log("Процес {$pid} для пари {$pair} завершено");
                } else {
                    $this->logger->log("Процес {$pid} для пари {$pair} не завершився, вбиваємо примусово");
                    posix_kill($pid, SIGKILL);
                }
            } else {
                $this->logger->log("Не вдалося надіслати SIGTERM до процесу {$pid} для пари {$pair}, можливо він вже завершився");
            }
            
            unlink($file);
            @unlink("{$this->pidDir}/{$pairName}.lock"); // Видаляємо лок-файл
            $this->logger->log("Процес для пари {$pair} зупинено, PID-файл і лок-файл видалено");
        }
        
        $this->logger->log("Всі процеси ботів зупинено");
    }
    
    /**
     * Starting processes for all active pairs
     */
    public function startAllProcesses(): void
    {
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("Запуск процесів для всіх активних пар: " . implode(", ", $enabledPairs));
        
        // First, clean invalid PID files
        $this->cleanupInvalidPidFiles();
        $this->logger->log("Очищено недійсні PID-файли");
        
        // Reloading the configuration
        Config::reloadConfig();
        $this->logger->log("Перезавантажено конфігурацію");
        
        // Getting the list of active pairs
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("Активні пари після перезавантаження конфігурації: " . implode(", ", $enabledPairs));
        
        // Stopping processes for inactive pairs
        $pidFiles = glob($this->pidDir . '/*.pid');
        $this->logger->log("Існуючі PID-файли: " . implode(", ", $pidFiles));
        
        foreach ($pidFiles as $pidFile) {
            $pairName = basename($pidFile, '.pid');
            $pair = $this->restorePairFormat($pairName);
            $this->logger->log("Перевірка пари з PID-файлу: {$pair}");
            
            if (!in_array($pair, $enabledPairs)) {
                $this->logger->log("Пара {$pair} вимкнена, зупиняємо процес");
                $this->stopProcess($pair);
            } else {
                $this->logger->log("Пара {$pair} активна, залишаємо процес");
            }
        }
        
        // Starting processes for active pairs
        foreach ($enabledPairs as $pair) {
            if (!$this->isProcessRunning($pair)) {
                $this->logger->log("Пара {$pair} активна, але процес не запущений. Запускаємо процес.");
                $this->startProcess($pair);
            } else {
                $this->logger->log("Пара {$pair} активна, процес вже запущений.");
            }
        }
        
        $this->logger->log("Всі процеси ботів для активних пар запущено");
    }
    
    /**
     * Updating the list of processes
     */
    public function updateProcesses(): void
    {
        $this->logger->info("!!!!! Оновлення процесів на основі активних пар");
        
        // Отримуємо список активних пар
        $pairs = Config::getEnabledPairs();
        $this->logger->info("!!!!! Активні пари: " . implode(", ", $pairs ?: []));
        
        // Отримуємо список запущених процесів
        $runningProcesses = $this->getRunningProcesses();
        
        // Перевіряємо кожну активну пару
        foreach ($pairs as $pair) {
            // Отримуємо поточний хеш конфігурації для пари
            $config = Config::getPairConfig($pair);
            
            if (empty($config)) {
                $this->logger->error("!!!!! Відсутня конфігурація для пари $pair");
                continue;
            }
            
            $configHash = md5(json_encode($config));
            $oldConfigHash = $this->pairConfigHashes[$pair] ?? null;
            
            $this->logger->info("!!!!! Пара: $pair, Старий хеш: " . ($oldConfigHash ?: 'відсутній') . ", Новий хеш: $configHash");
            
            // Перевіряємо, чи змінилася конфігурація або чи не запущений процес
            if ($oldConfigHash !== $configHash || !in_array($pair, $runningProcesses)) {
                // Якщо процес вже запущено, зупиняємо його перед перезапуском
                if (in_array($pair, $runningProcesses)) {
                    $this->logger->info("!!!!! Конфігурація для пари $pair змінилася. Зупиняємо процес для перезапуску.");
                    $this->stopProcess($pair);
                } else {
                    $this->logger->info("!!!!! Процес для пари $pair не запущений. Запускаємо новий процес.");
                }
                
                // Запускаємо процес з новою конфігурацією
                $this->startProcess($pair);
                
                // Зберігаємо новий хеш конфігурації
                $this->pairConfigHashes[$pair] = $configHash;
                $this->logger->info("!!!!! Оновлено хеш конфігурації для пари $pair");
            } else {
                $this->logger->info("!!!!! Конфігурація для пари $pair не змінилася. Процес працює, перезапуск не потрібен.");
            }
        }
        
        // Перевіряємо, чи є зайві процеси, які потрібно зупинити
        foreach ($runningProcesses as $runningPair) {
            if (!in_array($runningPair, $pairs)) {
                $this->logger->info("!!!!! Пара $runningPair більше не активна. Зупиняємо процес.");
                $this->stopProcess($runningPair);
                
                // Видаляємо хеш конфігурації для неактивної пари
                if (isset($this->pairConfigHashes[$runningPair])) {
                    unset($this->pairConfigHashes[$runningPair]);
                    $this->logger->info("!!!!! Видалено хеш конфігурації для неактивної пари $runningPair");
                }
            }
        }
        
        $this->logger->info("!!!!! Оновлення процесів завершено");
    }
    
    /**
     * Cleaning invalid PID files
     */
    public function cleanupInvalidPidFiles(): void
    {
        $this->logger->log("Очищення невірних PID файлів...");
        $pidFiles = glob("{$this->pidDir}/*.pid");
        
        if (empty($pidFiles)) {
            $this->logger->log("PID файли не знайдено");
        } else {
            $this->logger->log("Знайдено PID файли: " . implode(", ", $pidFiles));
        }
        
        foreach ($pidFiles as $pidFile) {
            $pair = basename($pidFile, '.pid');
            $pid = (int)file_get_contents($pidFile);
            $this->logger->log("Перевірка PID {$pid} для пари {$pair}");
            
            if ($pid <= 0) {
                $this->logger->log("Видалення невірного PID файлу для пари {$pair} (некоректний PID: {$pid})");
                unlink($pidFile);
                continue;
            }
            
            // Пропускаємо нові файли (менше 10 секунд)
            $fileCreationTime = filemtime($pidFile);
            if ((time() - $fileCreationTime) < 10) {
                $this->logger->log("PID-файл для пари {$pair} з PID {$pid} створено недавно, пропускаємо");
                continue;
            }
            
            // Перевірка через posix_kill і ps
            if (!posix_kill($pid, 0)) {
                exec("ps -p {$pid} -o pid=", $output);
                if (empty($output)) {
                    $this->logger->log("Видалення PID файлу для неіснуючого процесу {$pid} для пари {$pair}");
                    unlink($pidFile);
                } else {
                    $this->logger->log("Процес {$pid} для пари {$pair} ще живий (ps)");
                }
            } else {
                $this->logger->log("Процес {$pid} для пари {$pair} живий (posix_kill)");
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
    
    /**
     * Отримання списку всіх запущених процесів (пар)
     *
     * @return array Масив з назвами пар, для яких запущені процеси
     */
    public function getRunningProcesses(): array
    {
        $this->logger->info("!!!!! Отримання списку запущених процесів");
        $runningProcesses = [];
        
        $pidFiles = glob($this->pidDir . '/*.pid');
        $this->logger->info("!!!!! Знайдено PID-файли: " . implode(", ", $pidFiles ?: []));
        
        foreach ($pidFiles as $pidFile) {
            $pairName = basename($pidFile, '.pid');
            
            try {
                $pair = $this->restorePairFormat($pairName);
                
                if ($this->isProcessRunning($pair)) {
                    $runningProcesses[] = $pair;
                } else {
                    // Якщо PID-файл існує, але процес не запущений, видаляємо файл
                    $this->logger->info("!!!!! PID-файл для пари $pair існує, але процес не запущений. Видаляємо файл.");
                    unlink($pidFile);
                }
            } catch (Exception $e) {
                $this->logger->error("!!!!! Помилка обробки PID файлу для пари $pairName: " . $e->getMessage());
                // Видаляємо проблемний PID-файл
                if (file_exists($pidFile)) {
                    $this->logger->info("!!!!! Видаляємо проблемний PID файл $pidFile");
                    unlink($pidFile);
                }
            }
        }
        
        $this->logger->info("!!!!! Запущені процеси: " . implode(", ", $runningProcesses ?: []));
        return $runningProcesses;
    }
} 