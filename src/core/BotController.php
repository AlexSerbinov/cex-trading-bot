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
     * Вивід довідки
     */
    public function showHelp(): void
    {
        echo "Використання:\n";
        echo "  php BotController.php <команда> [аргументи]\n\n";
        echo "Доступні команди:\n";
        echo "  start <пара>       - Запустити бота для вказаної пари\n";
        echo "  stop <пара>        - Зупинити бота для вказаної пари\n";
        echo "  restart <пара>     - Перезапустити бота для вказаної пари\n";
        echo "  start-all          - Запустити всіх ботів для активних пар\n";
        echo "  stop-all           - Зупинити всіх ботів\n";
        echo "  status [пара]      - Перевірити статус бота для пари або всіх ботів\n";
        echo "  list               - Вивести список активних пар в конфігурації\n";
        echo "  check-config       - Перевірити конфігурацію\n";
        echo "  update-processes   - Оновити всі процеси ботів згідно поточної конфігурації\n";
        echo "  start-manager      - Запустити менеджер процесів ботів\n";
        echo "  help               - Показати цю довідку\n";
    }
    
    /**
     * Запуск бота для пари
     */
    public function startBot(string $pair): void
    {
        $this->logger->log("Запуск бота для пари {$pair}");
        
        if (empty($pair)) {
            echo "Помилка: Пара не вказана\n";
            exit(1);
        }
        
        // Перевірка, чи пара існує та активна
        Config::reloadConfig();
        $enabledPairs = Config::getEnabledPairs();
        
        if (!in_array($pair, $enabledPairs)) {
            echo "Помилка: Пара {$pair} не активна або не існує в конфігурації\n";
            exit(1);
        }
        
        if ($this->botProcess->isProcessRunning($pair)) {
            echo "Бот для пари {$pair} вже запущений\n";
        } else {
            if ($this->botProcess->startProcess($pair)) {
                echo "Бот для пари {$pair} успішно запущений\n";
            } else {
                echo "Помилка: Не вдалося запустити бот для пари {$pair}\n";
                exit(1);
            }
        }
    }
    
    /**
     * Зупинка бота для пари
     */
    public function stopBot(string $pair): void
    {
        $this->logger->log("Зупинка бота для пари {$pair}");
        
        if (empty($pair)) {
            echo "Помилка: Пара не вказана\n";
            exit(1);
        }
        
        if (!$this->botProcess->isProcessRunning($pair)) {
            echo "Бот для пари {$pair} не запущений\n";
        } else {
            if ($this->botProcess->stopProcess($pair)) {
                echo "Бот для пари {$pair} успішно зупинений\n";
            } else {
                echo "Помилка: Не вдалося зупинити бот для пари {$pair}\n";
                exit(1);
            }
        }
    }
    
    /**
     * Перезапуск бота для пари
     */
    public function restartBot(string $pair): void
    {
        $this->logger->log("Перезапуск бота для пари {$pair}");
        
        if (empty($pair)) {
            echo "Помилка: Пара не вказана\n";
            exit(1);
        }
        
        // Перевірка, чи пара існує та активна
        Config::reloadConfig();
        $enabledPairs = Config::getEnabledPairs();
        
        if (!in_array($pair, $enabledPairs)) {
            echo "Помилка: Пара {$pair} не активна або не існує в конфігурації\n";
            exit(1);
        }
        
        // Зупиняємо бота, якщо запущений
        if ($this->botProcess->isProcessRunning($pair)) {
            echo "Зупиняємо бот для пари {$pair}...\n";
            if (!$this->botProcess->stopProcess($pair)) {
                echo "Помилка: Не вдалося зупинити бот для пари {$pair}\n";
                exit(1);
            }
            // Затримка перед запуском
            sleep(2);
        }
        
        // Запускаємо бота
        echo "Запускаємо бот для пари {$pair}...\n";
        if ($this->botProcess->startProcess($pair)) {
            echo "Бот для пари {$pair} успішно перезапущений\n";
        } else {
            echo "Помилка: Не вдалося запустити бот для пари {$pair}\n";
            exit(1);
        }
    }
    
    /**
     * Запуск всіх ботів
     */
    public function startAllBots(): void
    {
        $this->logger->log("Запуск всіх ботів для активних пар");
        
        // Спочатку оновлюємо конфігурацію
        Config::reloadConfig();
        
        // Отримуємо активні пари
        $enabledPairs = Config::getEnabledPairs();
        
        if (empty($enabledPairs)) {
            echo "Немає активних пар в конфігурації\n";
            exit(0);
        }
        
        echo "Запуск ботів для наступних пар: " . implode(", ", $enabledPairs) . "\n";
        
        // Запускаємо боти для кожної активної пари
        $successCount = 0;
        $failCount = 0;
        
        foreach ($enabledPairs as $pair) {
            echo "Запуск бота для пари {$pair}...\n";
            
            if ($this->botProcess->isProcessRunning($pair)) {
                echo "Бот для пари {$pair} вже запущений\n";
                $successCount++;
            } else {
                if ($this->botProcess->startProcess($pair)) {
                    echo "Бот для пари {$pair} успішно запущений\n";
                    $successCount++;
                    // Невелика затримка між запусками
                    usleep(500000); // 0.5 секунди
                } else {
                    echo "Помилка: Не вдалося запустити бот для пари {$pair}\n";
                    $failCount++;
                }
            }
        }
        
        echo "Запуск ботів завершено. Успішно: {$successCount}, з помилками: {$failCount}\n";
    }
    
    /**
     * Зупинка всіх ботів
     */
    public function stopAllBots(): void
    {
        $this->logger->log("Зупинка всіх ботів");
        $this->botProcess->stopAllProcesses();
        echo "Всі боти зупинені\n";
    }
    
    /**
     * Перевірка статусу ботів
     */
    public function checkStatus(?string $pair = null): void
    {
        $this->logger->log("Перевірка статусу ботів");
        
        // Якщо вказана конкретна пара
        if (!empty($pair)) {
            if ($this->botProcess->isProcessRunning($pair)) {
                echo "Бот для пари {$pair} запущений\n";
            } else {
                echo "Бот для пари {$pair} не запущений\n";
            }
            return;
        }
        
        // Перевіряємо всі активні пари
        Config::reloadConfig();
        $enabledPairs = Config::getEnabledPairs();
        
        if (empty($enabledPairs)) {
            echo "Немає активних пар в конфігурації\n";
            return;
        }
        
        echo "Статус ботів для активних пар:\n";
        echo "--------------------------------\n";
        
        $runningCount = 0;
        $stoppedCount = 0;
        
        foreach ($enabledPairs as $pair) {
            $status = $this->botProcess->isProcessRunning($pair) ? "запущений" : "зупинений";
            echo "{$pair}: {$status}\n";
            
            if ($status === "запущений") {
                $runningCount++;
            } else {
                $stoppedCount++;
            }
        }
        
        echo "--------------------------------\n";
        echo "Всього: " . count($enabledPairs) . ", запущено: {$runningCount}, зупинено: {$stoppedCount}\n";
    }
    
    /**
     * Вивід списку активних пар
     */
    public function listActivePairs(): void
    {
        Config::reloadConfig();
        $enabledPairs = Config::getEnabledPairs();
        
        if (empty($enabledPairs)) {
            echo "Немає активних пар в конфігурації\n";
            return;
        }
        
        echo "Активні пари в конфігурації:\n";
        echo "----------------------------\n";
        
        foreach ($enabledPairs as $pair) {
            $pairConfig = Config::getPairConfig($pair);
            
            if ($pairConfig) {
                $frequency_from = $pairConfig['settings']['frequency_from'];
                $frequency_to = $pairConfig['settings']['frequency_to'];
                
                echo "{$pair}: frequency_from={$frequency_from}, frequency_to={$frequency_to}\n";
            } else {
                echo "{$pair}: конфігурація не знайдена\n";
            }
        }
    }
    
    /**
     * Перевірка конфігурації
     */
    public function checkConfig(): void
    {
        $this->logger->log("Перевірка конфігурації");
        
        try {
            Config::reloadConfig();
            $configFile = __DIR__ . '/../../config/bots_config.json';
            
            if (!file_exists($configFile)) {
                echo "Помилка: Файл конфігурації не знайдений: {$configFile}\n";
                exit(1);
            }
            
            $configString = file_get_contents($configFile);
            
            if (empty($configString)) {
                echo "Помилка: Файл конфігурації порожній\n";
                exit(1);
            }
            
            $config = json_decode($configString, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "Помилка: Невірний формат JSON в файлі конфігурації: " . json_last_error_msg() . "\n";
                exit(1);
            }
            
            $enabledPairs = Config::getEnabledPairs();
            
            echo "Конфігурація перевірена успішно\n";
            echo "Файл конфігурації: {$configFile}\n";
            echo "Останнє оновлення: " . date('Y-m-d H:i:s', filemtime($configFile)) . "\n";
            echo "Активні пари: " . implode(", ", $enabledPairs) . "\n";
            
        } catch (Exception $e) {
            echo "Помилка при перевірці конфігурації: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    /**
     * Оновити процеси ботів
     */
    public function updateProcesses(): void
    {
        $this->logger->log("Оновлення процесів ботів");
        
        try {
            $this->botProcess->updateProcesses();
            echo "Процеси ботів успішно оновлені\n";
            
        } catch (Exception $e) {
            echo "Помилка при оновленні процесів ботів: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    /**
     * Запуск менеджера процесів
     */
    public function startManager(): void
    {
        $this->logger->log("Запуск менеджера процесів ботів");
        
        // Перевіряємо, чи менеджер вже запущений
        $scriptPath = __DIR__ . '/TradingBotProcessManager.php';
        
        if (!file_exists($scriptPath)) {
            echo "Помилка: Скрипт менеджера процесів не знайдений: {$scriptPath}\n";
            exit(1);
        }
        
        // Команда для запуску менеджера процесів у фоновому режимі
        $command = sprintf(
            'php %s > /dev/null 2>&1 & echo $!',
            escapeshellarg($scriptPath)
        );
        
        echo "Запуск менеджера процесів...\n";
        $pid = exec($command);
        
        if (empty($pid)) {
            echo "Помилка: Не вдалося запустити менеджер процесів\n";
            exit(1);
        }
        
        echo "Менеджер процесів успішно запущений з PID: {$pid}\n";
    }
}

// Виконання команди
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
            echo "Помилка: Не вказана пара\n";
            exit(1);
        }
        $controller->startBot($argv[2]);
        break;
    
    case 'stop':
        if ($argc < 3) {
            echo "Помилка: Не вказана пара\n";
            exit(1);
        }
        $controller->stopBot($argv[2]);
        break;
    
    case 'restart':
        if ($argc < 3) {
            echo "Помилка: Не вказана пара\n";
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
        echo "Невідома команда: {$command}\n";
        $controller->showHelp();
        exit(1);
} 