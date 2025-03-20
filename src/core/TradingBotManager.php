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
        $this->logger = Logger::getInstance();
        $this->logger->log("Ініціалізація TradingBotManager, PID=" . getmypid());
        $this->botProcess = new BotProcess();
        $this->configFile = __DIR__ . '/../../config/bots_config.json';
        $this->logger->log("Інтервал перевірки встановлено на {$this->checkInterval} секунд");
        
        // Registering a signal handler for proper termination
        $this->logger->log("Реєстрація обробників сигналів");
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        // Adding SIGHUP handling for better compatibility
        pcntl_signal(SIGHUP, [$this, 'handleSignal']);
        $this->logger->log("TradingBotManager успішно ініціалізовано");
    }

    /**
     * Signal handler
     */
    public function handleSignal(int $signal): void
    {
        $pidDir = __DIR__ . '/../../data/pids';
        $pidFiles = is_dir($pidDir) ? glob($pidDir . '/*.pid') : [];
        $this->logger->log("Отримано сигнал {$signal}, зупиняємо всі боти. PID-файли: " . implode(", ", $pidFiles));
        
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
     * Метод для очищення всіх ордерів для активних пар
     */
    public function clearAllOrdersForActivePairs(): void
    {
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("Очищення ордерів для активних пар: " . implode(", ", $enabledPairs));
        
        foreach ($enabledPairs as $pair) {
            $this->logger->log("Блокування для очищення ордерів пари {$pair}...");
            $lockFile = __DIR__ . "/../../data/locks/{$pair}_cleaning.lock";
            $lockHandle = fopen($lockFile, 'c');
            
            if (!$lockHandle) {
                $this->logger->log("Помилка створення файлу блокування для пари {$pair}");
                continue;
            }
            
            $locked = flock($lockHandle, LOCK_EX | LOCK_NB);
            $this->logger->log("Статус блокування для пари {$pair}: " . ($locked ? "успішно" : "заблоковано"));
            
            if ($locked) {
                try {
                    // Code for clearing orders for the pair
                    $this->logger->log("Виконується очищення ордерів для пари {$pair}...");
                    // Add your order clearing code here
                    $this->logger->log("Очищення ордерів для пари {$pair} завершено успішно");
                } catch (Exception $e) {
                    $this->logger->log("Помилка при очищенні ордерів для пари {$pair}: " . $e->getMessage());
                } finally {
                    flock($lockHandle, LOCK_UN);
                    fclose($lockHandle);
                    $this->logger->log("Розблокування для пари {$pair} виконано");
                }
            } else {
                $this->logger->log("Неможливо отримати блокування для пари {$pair}, пропускаємо очищення");
                fclose($lockHandle);
            }
        }
    }

    /**
     * Running all bots
     */
    public function runAllBots(): void
    {
        $this->logger->log("Запуск менеджера ботів у режимі паралельного виконання");
        
        // Forcefully clearing and reloading the configuration
        Config::reloadConfig();
        
        // First, stop all existing processes
        $pidDir = __DIR__ . '/../../data/pids';
        $pidFiles = is_dir($pidDir) ? glob($pidDir . '/*.pid') : [];
        $this->logger->log("Зупинка всіх існуючих процесів. Знайдені PID-файли: " . implode(", ", $pidFiles));
        
        $this->botProcess->stopAllProcesses();
        $this->logger->log("Всі існуючі процеси зупинено");
        
        // Очищення всіх ордерів для активних пар
        $this->logger->log("Початок очищення ордерів для всіх активних пар");
        $this->clearAllOrdersForActivePairs();
        $this->logger->log("Очищення ордерів для всіх активних пар завершено");
        
        // Start processes for all active pairs
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("Запуск процесів для активних пар: " . implode(", ", $enabledPairs));
        
        $this->botProcess->startAllProcesses();
        $this->logger->log("Всі процеси для активних пар запущено");
        
        // Remembering the last modification time of the configuration
        $this->lastConfigModTime = file_exists($this->configFile) ? filemtime($this->configFile) : 0;
        
        // Встановлюємо час останнього оновлення на поточний,
        // щоб уникнути подвійного запуску процесів відразу після їх створення
        $lastUpdateTime = time();
        $this->logger->log("Час останнього оновлення встановлено на {$lastUpdateTime}, наступне оновлення через 60 секунд");
        
        // Main loop of the manager
        while (true) {
            // Forcefully reloading the configuration
            Config::reloadConfig();
            $currentTime = time();
            
            // Checking if the configuration has changed
            if (file_exists($this->configFile)) {
                $currentModTime = filemtime($this->configFile);
                if ($currentModTime > $this->lastConfigModTime) {
                    $this->logger->log("Виявлено зміни в конфігурації, оновлюємо процеси");
                    $this->botProcess->updateProcesses();
                    $this->lastConfigModTime = $currentModTime;
                }
            }
            
            // Forcefully updating processes every 60 seconds
            // Використовуємо статичну змінну з початковим значенням, яке ми встановили раніше
            if ($currentTime - $lastUpdateTime >= 60) {
                $this->logger->log("Планове оновлення процесів (кожні 60 секунд)");
                $this->logger->log("Час від останнього оновлення: " . ($currentTime - $lastUpdateTime) . " секунд");
                
                // Спочатку очищаємо недійсні PID-файли
                $this->botProcess->cleanupInvalidPidFiles();
                $this->botProcess->updateProcesses();
                $lastUpdateTime = $currentTime;
                $this->logger->log("Планове оновлення процесів завершено");
            }
            
            // Short delay before the next check
            $this->logger->log("Очікування {$this->checkInterval} секунд до наступної перевірки...");
            sleep($this->checkInterval);
        }
    }
}

// Running the bot manager if the file is called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $logger = Logger::getInstance();
    $logger->log("=== ЗАПУСК TRADING BOT MANAGER ===");
    
    // Перевіряємо, чи вже запущений TradingBotManager
    $lockFile = __DIR__ . '/../../data/pids/trading_bot_manager.lock';
    $logger->log("Перевірка лок-файлу: {$lockFile}");
    
    // Docker-специфічна перевірка запущених процесів
    if (file_exists($lockFile)) {
        $pid = (int)file_get_contents($lockFile);
        $logger->log("Знайдено існуючий лок-файл з PID: {$pid}");
        
        // Перевіряємо, чи процес із цим PID ще живий
        $processExists = false;
        
        // Перевірка методом 1: через /proc (основний метод у Linux)
        if (file_exists("/proc/{$pid}")) {
            $logger->log("PID {$pid} існує в /proc");
            
            // Додаткова перевірка через cmdline
            if (file_exists("/proc/{$pid}/cmdline")) {
                $cmdline = file_get_contents("/proc/{$pid}/cmdline");
                $logger->log("Командний рядок процесу: " . $cmdline);
                
                if (strpos($cmdline, 'TradingBotManager') !== false) {
                    $processExists = true;
                    $logger->log("Процес підтверджено як TradingBotManager");
                }
            }
        }
        
        // Перевірка методом 2: через функцію posix_kill (якщо доступна)
        if (function_exists('posix_kill')) {
            // Сигнал 0 - перевірка існування процесу без відправки сигналу
            if (posix_kill($pid, 0)) {
                $logger->log("PID {$pid} підтверджено через posix_kill");
                $processExists = true;
            } else {
                $logger->log("PID {$pid} не існує за перевіркою posix_kill");
            }
        }
        
        if ($processExists) {
            $logger->log("TradingBotManager вже запущений з PID {$pid}. Виходимо.");
            echo "TradingBotManager вже запущений з PID {$pid}. Виходимо.\n";
            exit(0);
        } else {
            $logger->log("Процес з PID {$pid} не виявлено або це не TradingBotManager. Видаляємо старий лок-файл.");
            unlink($lockFile);
        }
    } else {
        $logger->log("Лок-файл не знайдено. Створюємо новий.");
    }
    
    // Зберігаємо поточний PID
    $currentPid = getmypid();
    $logger->log("Створюємо новий лок-файл з PID: {$currentPid}");
    file_put_contents($lockFile, $currentPid);
    $logger->log("Збережено поточний PID {$currentPid} в лок-файл");
    
    $manager = new TradingBotManager();
    $logger->log("Створено екземпляр TradingBotManager");
    $manager->runAllBots();
}