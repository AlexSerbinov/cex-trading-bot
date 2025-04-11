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
        
        // Отримуємо конфігурацію для пари
        $config = Config::getPairConfig($pair);
        if (!$config) {
            $this->logger->error("Не вдалося отримати конфігурацію для пари {$pair}, запуск неможливий");
            return false;
        }
        
        // Зберігаємо хеш налаштувань для цієї пари
        $safePair = $this->formatPairForFileName($pair);
        $configSettingsHash = md5(json_encode($config['settings'] ?? []));
        $configHashFile = $this->pidDir . "/{$safePair}_config_hash.txt";
        file_put_contents($configHashFile, $configSettingsHash);
        $this->logger->log("Збережено хеш конфігурації для пари {$pair}: {$configSettingsHash}");
        
        // Спочатку завжди очищаємо ордери для пари
        $this->logger->log("Очищення всіх ордерів для пари {$pair} перед запуском процесу");
        require_once __DIR__ . '/TradingBot.php';
        try {
            if ($config) {
                $tempBot = new TradingBot($pair, $config);
                $tempBot->clearAllOrders();
                $this->logger->log("Ордери для пари {$pair} успішно очищені");
            } else {
                $this->logger->error("Не вдалося отримати конфігурацію для пари {$pair}, очищення ордерів пропущено");
            }
        } catch (Exception $e) {
            $this->logger->error("Помилка при очищенні ордерів для пари {$pair}: " . $e->getMessage());
            // Продовжуємо запуск процесу, навіть якщо очищення не вдалося
        }
        
        // Check if the process is already running for this pair
        if ($this->isProcessRunning($pair)) {
            $this->logger->log("Процес для пари {$pair} вже запущений, зупиняємо його перед запуском нового");
            $this->stopProcess($pair);
            $this->logger->log("Очікуємо 1 секунду після зупинки процесу для пари {$pair}");
            sleep(1);
        }
        
        // Додаткова перевірка запущених процесів для цієї пари
        // Використовуємо більш надійний спосіб перевірки
        $pidFile = $this->getPidFilePath($this->formatPairForFileName($pair));
        $this->logger->log("Перевірка PID файлу для пари {$pair}: {$pidFile}");
        
        if (file_exists($pidFile)) {
            $pid = (int)file_get_contents($pidFile);
            $this->logger->log("Знайдено існуючий PID файл для пари {$pair} з PID: {$pid}");
            
            if ($pid > 0 && file_exists("/proc/{$pid}")) {
                $this->logger->log("Процес {$pid} для пари {$pair} існує, зупиняємо його перед запуском нового");
                $this->stopProcess($pair);
                $this->logger->log("Очікуємо 1 секунду після зупинки процесу для пари {$pair}");
                sleep(1);
            } else {
                $this->logger->log("PID файл для пари {$pair} існує, але процес {$pid} не запущений, видаляємо файл");
                unlink($pidFile);
            }
        } else {
            $this->logger->log("PID файл для пари {$pair} не існує");
        }
        
        // Перевірка на запущені процеси BotRunner для цієї пари через proc
        $this->logger->log("Перевірка на запущені процеси BotRunner для пари {$pair}");
        $runningProcesses = $this->findRunningBotRunnerProcesses($pair);
        
        if (!empty($runningProcesses)) {
            $this->logger->log("Знайдено запущені процеси BotRunner для пари {$pair}: " . implode(", ", $runningProcesses));
            foreach ($runningProcesses as $pid) {
                $this->logger->log("Зупиняємо процес {$pid} для пари {$pair}");
                if (function_exists('posix_kill')) {
                    $killResult = posix_kill($pid, SIGTERM);
                    $this->logger->log("Результат posix_kill для PID {$pid} пари {$pair}: " . ($killResult ? "успішно" : "невдало"));
                } else {
                    exec("kill -15 {$pid}");
                    $this->logger->log("Виконано команду kill -15 для PID {$pid} пари {$pair}");
                }
            }
            $this->logger->log("Очікуємо 1 секунду після зупинки процесів для пари {$pair}");
            sleep(1);
        } else {
            $this->logger->log("Не знайдено запущених процесів BotRunner для пари {$pair}");
        }
        
        // Path to the script that will be executed in a separate process
        $scriptPath = __DIR__ . '/BotRunner.php';
        
        // Format the pair for the PID file name
        $safePair = $this->formatPairForFileName($pair);
        
        // Acquire a lock to ensure no parallel process starts for this pair
        $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
        $this->logger->log("Спроба блокування для пари {$pair}, лок-файл: {$lockFile}");
        
        // Перевіряємо, чи існує вже лок-файл і чи він заблокований
        if (file_exists($lockFile)) {
            $existingLockHandle = @fopen($lockFile, 'r');
            if ($existingLockHandle) {
                $locked = !flock($existingLockHandle, LOCK_EX | LOCK_NB);
                fclose($existingLockHandle);
                
                if ($locked) {
                    $this->logger->log("Лок-файл для пари {$pair} вже заблокований. Інший процес вже запущений або запускається.");
                    return false;
                } else {
                    $this->logger->log("Лок-файл для пари {$pair} існує, але не заблокований. Видаляємо його.");
                    unlink($lockFile);
                }
            }
        }
        
        $lockHandle = fopen($lockFile, 'c');
        
        if (!$lockHandle) {
            $this->logger->error("Не вдалося створити лок-файл для пари {$pair}");
            return false;
        }
        
        $locked = flock($lockHandle, LOCK_EX | LOCK_NB);
        $this->logger->log("Статус блокування для пари {$pair}: " . ($locked ? "успішно" : "заблоковано"));
        
        if (!$locked) {
            $this->logger->log("Не вдалося отримати блокування для пари {$pair}, можливо інший процес вже запускається");
            fclose($lockHandle);
            return false;
        }
        
        // Зберігаємо хендл лок-файлу в додатковому файлі для подальшого використання
        $lockHandleFile = $this->pidDir . "/{$safePair}_lock_handle.txt";
        file_put_contents($lockHandleFile, "locked");
        
        try {
            // Command to start the process
            $command = sprintf(
                'php %s %s > /dev/null 2>&1 & echo $!',
                escapeshellarg($scriptPath),
                escapeshellarg($pair)
            );
            
            $this->logger->log("Виконуємо команду для запуску процесу для пари {$pair}: {$command}");
            
            // Execute the command and get the PID of the process
            $pid = exec($command);
            
            if (empty($pid)) {
                $this->logger->error("Не вдалося запустити процес для пари {$pair}");
                return false;
            }
            
            $this->logger->log("Отримано PID для пари {$pair}: {$pid}");
            
            // Save the PID of the process
            $this->savePid($safePair, (int) $pid);
            
            // Зберігаємо інформацію про лок-файл разом з PID
            file_put_contents($lockFile, $pid);
            
            // Додаємо невелику затримку після запуску процесу і збереження PID
            // щоб гарантувати, що файл буде коректно створено і процес встигне розпочати роботу
            // перш ніж буде викликана перевірка чи запущений процес
            usleep(500000); // 500 мс = 0.5 секунди
            
            $this->logger->log("Процес для пари {$pair} успішно запущено з PID: {$pid}");
            return true;
        } catch (Exception $e) {
            $this->logger->error("Помилка при запуску процесу для пари {$pair}: " . $e->getMessage());
            // Release the lock if an error occurred
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($lockFile);
            @unlink($lockHandleFile);
            return false;
        }
        
        // ВАЖЛИВО: Ми не закриваємо лок-файл, щоб підтримувати блокування протягом всього часу роботи процесу
        // Лок буде автоматично знято, коли процес завершиться або буде зупинений через stopProcess()
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
        
        $this->logger->log("Шлях до PID файлу для пари {$pair}: {$pidFile}");
        
        // If the PID file does not exist, the process is not running
        if (!file_exists($pidFile)) {
            $this->logger->log("PID файл не існує для пари {$pair}, процес не запущений");
            
            // Перевіряємо, чи існує лок-файл і видаляємо його
            $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
            if (file_exists($lockFile)) {
                $this->logger->log("Видаляємо лок-файл для пари {$pair}: {$lockFile}");
                @unlink($lockFile);
            }
            
            return true;
        }
        
        // Reading the PID from the file
        $pid = (int)file_get_contents($pidFile);
        
        if ($pid <= 0) {
            $this->logger->log("Невірний PID ({$pid}) у файлі для пари {$pair}");
            unlink($pidFile);
            
            // Видаляємо лок-файл
            $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
            if (file_exists($lockFile)) {
                $this->logger->log("Видаляємо лок-файл для пари {$pair}: {$lockFile}");
                @unlink($lockFile);
            }
            
            return true;
        }
        
        $this->logger->log("Знайдено PID: {$pid} для зупинки пари {$pair}");
        
        // Trying to stop the process gracefully first (SIGTERM)
        $this->logger->log("Надсилаємо сигнал SIGTERM до процесу {$pid} для пари {$pair}");
        $sigResult = false;
        if (function_exists('posix_kill')) {
            $sigResult = posix_kill($pid, SIGTERM);
            $this->logger->log("Результат posix_kill для пари {$pair}: " . ($sigResult ? "успішно" : "невдало"));
        } else {
            // Fallback для систем без posix_kill (Docker)
            $this->logger->log("posix_kill не доступний для пари {$pair}, використовуємо kill -15");
            exec("kill -15 {$pid}", $output, $retval);
            $sigResult = ($retval === 0);
            $this->logger->log("Результат kill -15 для пари {$pair}: " . ($sigResult ? "успішно" : "невдало") . ", код: {$retval}");
        }
        
        // Waiting for the process to exit
        $this->logger->log("Очікуємо завершення процесу {$pid} для пари {$pair}...");
        $maxWait = 5; // максимальний час очікування у секундах
        $waited = 0;
        while ($waited < $maxWait) {
            if (!file_exists("/proc/{$pid}")) {
                $this->logger->log("Процес {$pid} для пари {$pair} завершено за {$waited} секунд");
                break;
            }
            sleep(1);
            $waited++;
        }
        
        // If the process is still running, forcefully terminate it (SIGKILL)
        if (file_exists("/proc/{$pid}")) {
            $this->logger->log("Процес {$pid} для пари {$pair} все ще працює після {$waited} секунд, надсилаємо SIGKILL");
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGKILL);
            } else {
                exec("kill -9 {$pid}");
            }
            $this->logger->log("Надіслано SIGKILL до процесу {$pid} для пари {$pair}");
            sleep(1);
        }
        
        // Removing the PID file
        if (file_exists($pidFile)) {
            $this->logger->log("Видаляємо PID файл {$pidFile} для пари {$pair}");
            unlink($pidFile);
        }
        
        // Видаляємо лок-файл
        $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
        if (file_exists($lockFile)) {
            $this->logger->log("Видаляємо лок-файл для пари {$pair}: {$lockFile}");
            @unlink($lockFile);
        }
        
        // Видаляємо файл хендлера лок-файлу
        $lockHandleFile = $this->pidDir . "/{$safePair}_lock_handle.txt";
        if (file_exists($lockHandleFile)) {
            $this->logger->log("Видаляємо файл хендлера лок-файлу для пари {$pair}: {$lockHandleFile}");
            @unlink($lockHandleFile);
        }
        
        // Verifying that the process is no longer running
        if (file_exists("/proc/{$pid}")) {
            $this->logger->log("УВАГА: Процес {$pid} для пари {$pair} все ще працює після всіх спроб зупинки");
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
        
        // Перевіряємо, чи існує лок-файл для цієї пари
        $lockFile = __DIR__ . "/../../data/locks/{$safePair}_process.lock";
        if (file_exists($lockFile)) {
            $this->logger->log("Знайдено лок-файл для пари {$pair}: {$lockFile}");
            
            // Перевіряємо, чи лок-файл заблокований
            $lockHandle = @fopen($lockFile, 'r');
            if ($lockHandle) {
                $lockStatus = !flock($lockHandle, LOCK_EX | LOCK_NB);
                fclose($lockHandle);
                
                if ($lockStatus) {
                    $this->logger->log("Лок-файл для пари {$pair} заблокований, процес вважається запущеним");
                    
                    // Якщо PID-файл існує, але лок-файл заблокований, це добре
                    // Якщо PID-файл не існує, але лок-файл заблокований, щось не так
                    if (!file_exists($pidFile)) {
                        $this->logger->log("УВАГА: Лок-файл для пари {$pair} заблокований, але PID-файл не існує");
                        // Спробуємо відновити PID-файл з лок-файлу
                        $pid = file_get_contents($lockFile);
                        if (is_numeric($pid) && $pid > 0) {
                            $this->logger->log("Відновлюємо PID-файл для пари {$pair} з лок-файлу, PID: {$pid}");
                            $this->savePid($safePair, (int) $pid);
                        }
                    }
                    
                    return true;
                } else {
                    $this->logger->log("Лок-файл для пари {$pair} не заблокований, перевіряємо PID-файл далі");
                }
            }
        } else {
            $this->logger->log("Лок-файл для пари {$pair} не знайдено");
        }
        
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
                    
                    // Якщо процес існує, але лок-файл не існує або не заблокований,
                    // спробуємо відновити лок-файл
                    if (!file_exists($lockFile)) {
                        $this->logger->log("УВАГА: Процес для пари {$pair} існує, але лок-файл не знайдено. Створюємо новий лок-файл.");
                        file_put_contents($lockFile, $pid);
                    }
                    
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
        
        // Також видаляємо лок-файл, якщо він існує
        if (file_exists($lockFile)) {
            $this->logger->log("Видаляємо лок-файл для пари {$pair}");
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
        // Forcefully reload the configuration
        Config::reloadConfig();
        
        // Getting the list of active pairs
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("Active pairs: " . implode(", ", $enabledPairs));
        
        // Отримуємо список запущених процесів для всіх пар
        $runningPairs = [];
        foreach ($enabledPairs as $pair) {
            if ($this->isProcessRunning($pair)) {
                $runningPairs[] = $pair;
            }
        }
        $this->logger->log("Поточні запущені процеси для пар: " . implode(", ", $runningPairs));
        
        // Отримуємо поточну конфігурацію ботів
        $configFile = __DIR__ . '/../../config/bots_config.json';
        $currentConfig = [];
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            if ($content !== false) {
                $currentConfig = json_decode($content, true) ?: [];
            }
        }
        
        // Зупиняємо процеси для неактивних пар
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
        
        // Запускаємо процеси для нових активних пар, які ще не запущені
        foreach ($enabledPairs as $pair) {
            try {
                if (!in_array($pair, $runningPairs)) {
                    $this->logger->log("Пара {$pair} активна, але не запущена. Запускаємо новий процес.");
                    // Використовуємо метод startProcess, який тепер очищає ордери перед запуском
                    $this->startProcess($pair);
                } else {
                    // Перевіряємо, чи змінилася конфігурація саме для цієї пари
                    $this->logger->log("Пара {$pair} вже має запущений процес, перевіряємо чи змінилась конфігурація для цієї пари.");
                    
                    $safePair = $this->formatPairForFileName($pair);
                    $pidFile = $this->getPidFilePath($safePair);
                    
                    if (file_exists($pidFile)) {
                        $pidFileModTime = filemtime($pidFile);
                        
                        // Перевіримо тільки зміни для цієї конкретної пари
                        $needRestart = false;
                        
                        // Перевіряємо наявність пари в новій конфігурації
                        if (isset($currentConfig[$pair])) {
                            // Пара існує в новій конфігурації, давайте перевіримо хеш налаштувань
                            $configSettingsHash = md5(json_encode($currentConfig[$pair]['settings'] ?? []));
                            
                            // Отримуємо поточний хеш налаштувань
                            $currentPairConfigFile = $this->pidDir . "/{$safePair}_config_hash.txt";
                            $storedHash = file_exists($currentPairConfigFile) ? file_get_contents($currentPairConfigFile) : '';
                            
                            if ($configSettingsHash !== $storedHash) {
                                $this->logger->log("Виявлено зміни в налаштуваннях для пари {$pair}. Перезапускаємо процес.");
                                $needRestart = true;
                            } else {
                                $this->logger->log("Конфігурація для пари {$pair} не змінювалась, залишаємо процес працювати.");
                            }
                        } else {
                            // Пара відсутня в новій конфігурації, це дивно, але ми маємо перезапустити процес
                            $this->logger->log("Пара {$pair} відсутня в конфігурації, але процес запущений. Перезапускаємо.");
                            $needRestart = true;
                        }
                        
                        if ($needRestart) {
                            // Зупиняємо процес
                            $this->stopProcess($pair);
                            // Запускаємо з очищенням ордерів
                            $this->startProcess($pair);
                            
                            // Зберігаємо новий хеш конфігурації
                            if (isset($currentConfig[$pair])) {
                                file_put_contents($currentPairConfigFile, $configSettingsHash);
                            }
                        }
                    }
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
} 