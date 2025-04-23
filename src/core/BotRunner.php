<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/TradingBot.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/BotProcess.php';

// Keep use statements for await, remove/ignore asyncSleep alias
use function React\Async\await;
// use function React\Promise\Timer\sleep as asyncSleep; // Keep or remove, doesn't matter now

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
    private $lastPairConfigHash = null; // Нове поле для зберігання хешу конфігурації пари

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
            $this->logger->log("BotRunner запущений у режимі менеджера процесів");
        } else {
            $this->logger->log("BotRunner запущений у режимі бота для пари {$this->pair}");
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
            $this->logger->log("Менеджер процесів отримав сигнал {$signal}, зупиняємо всі боти...");
            $this->botProcess->stopAllProcesses();
            $this->logger->log("Всі боти зупинені, завершуємо роботу менеджера");
        } else {
            $this->logger->log("Бот для пари {$this->pair} отримав сигнал {$signal}, зупиняємось");
            
            // Clearing all orders when stopping
            if (isset($this->bot)) {
                await($this->bot->clearAllOrders());
            }
            
            // Removing the PID file
            $pidFile = __DIR__ . '/../../data/pids/' . str_replace('_', '', $this->pair) . '.pid';
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
        }
        
        $this->terminate = true;
    }

    /**
     * Shut down the bot gracefully
     */
    private function shutdownGracefully()
    {
        if ($this->mode === 'manager') {
            $this->logger->log("Зупиняємо всі процеси ботів...");
            $this->botProcess->stopAllProcesses();
            $this->logger->log("Всі боти зупинені, завершуємо роботу менеджера");
        } else {
            $this->logger->log("Зупиняємо бот для пари {$this->pair}");
            
            // Clearing all orders when stopping
            if (isset($this->bot)) {
                await($this->bot->clearAllOrders());
            }
            
            // Removing the PID file
            $pidFile = __DIR__ . '/../../data/pids/' . str_replace('_', '', $this->pair) . '.pid';
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
            
            $this->logger->log("Бот для пари {$this->pair} зупинено");
        }
    }

    /**
     * Main execution loop of the trading bot.
     */
    public function run(): void
    {
        $this->logger->log("Запуск бота для пари {$this->pair} в окремому процесі");

        // Forcibly clearing and reloading the configuration
        $this->logger->log("!!!!! BotRunner: Початок перезавантаження конфігурації перед запуском бота");
        Config::reloadConfig();
        $this->logger->log("!!!!! BotRunner: Конфігурацію перезавантажено");

        // Checking if the pair exists and is active
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log("!!!!! BotRunner: Активні пари в конфігурації: " . implode(", ", $enabledPairs));
        if (!in_array($this->pair, $enabledPairs)) {
            $this->logger->error("!!!!! BotRunner: Пара {$this->pair} не знайдена в активних парах, зупиняємо бота");
            exit(1);
        }

        // Path to the configuration file
        $lastConfigModTime = file_exists($this->configFile) ? filemtime($this->configFile) : 0;
        $this->logger->log("!!!!! BotRunner: Час останньої модифікації конфігурації: " . date('Y-m-d H:i:s', $lastConfigModTime));

        try {
            // Getting the configuration for the pair
            $pairConfig = Config::getPairConfig($this->pair);
            
            if ($pairConfig === null) {
                $this->logger->error("!!!!! BotRunner: Конфігурація для пари {$this->pair} не знайдена");
                exit(1);
            }
            
            // Ініціалізуємо хеш конфігурації пари
            $this->lastPairConfigHash = md5(json_encode($pairConfig));
            $this->logger->log("!!!!! BotRunner: Початковий хеш конфігурації для пари {$this->pair}: {$this->lastPairConfigHash}");
            
            // Logging the values for verification
            $frequency_from = $pairConfig['settings']['frequency_from'];
            $frequency_to = $pairConfig['settings']['frequency_to'];
            
            $this->logger->log("!!!!! BotRunner: Завантажена конфігурація для {$this->pair}: frequency_from={$frequency_from}, frequency_to={$frequency_to}");
            $this->logger->log("!!!!! BotRunner: Повна конфігурація для бота: " . json_encode($pairConfig));
            
            // Creating a bot
            $this->logger->log("!!!!! BotRunner: Створення бота для пари {$this->pair}");
            $this->bot = new TradingBot($this->pair, $pairConfig);
            
            // Initializing the bot
            $this->logger->log("!!!!! BotRunner: Початок ініціалізації бота для пари {$this->pair}");
            await($this->bot->initialize());
            $this->logger->log("!!!!! BotRunner: Ініціалізацію бота для пари {$this->pair} завершено");
            
            // Main bot loop
            while (!$this->terminate) {
                try {
                    // Forcibly reloading the configuration on each cycle
                    $this->logger->log("!!!!! BotRunner: Перезавантаження конфігурації на початку циклу");
                    Config::reloadConfig();
                    
                    // Перевіряємо, чи пара все ще активна
                    $enabledPairs = Config::getEnabledPairs();
                    if (!in_array($this->pair, $enabledPairs)) {
                        $this->logger->log("!!!!! BotRunner: Пара {$this->pair} деактивована, зупиняємо бота");
                        break;
                    }
                    
                    // Отримуємо оновлену конфігурацію пари
                    $pairConfig = Config::getPairConfig($this->pair);
                    if ($pairConfig === null) {
                        $this->logger->error("!!!!! BotRunner: Конфігурація для пари {$this->pair} не знайдена під час оновлення");
                        break;
                    }
                    
                    // Розраховуємо новий хеш конфігурації
                    $currentPairConfigHash = md5(json_encode($pairConfig));
                    
                    // Перевіряємо, чи змінилася конфігурація
                    $configChanged = ($currentPairConfigHash !== $this->lastPairConfigHash);
                    
                    // Логуємо хеші для налагодження
                    $this->logger->log("!!!!! BotRunner: Поточний хеш конфігурації пари: {$currentPairConfigHash}, попередній: {$this->lastPairConfigHash}");
                    $this->logger->log("!!!!! BotRunner: Конфігурація пари змінилася: " . ($configChanged ? "ТАК" : "НІ"));
                    
                    if ($configChanged) {
                        $this->logger->log("!!!!! BotRunner: Виявлено зміни в конфігурації пари {$this->pair}");
                        
                        $frequency_from = $pairConfig['settings']['frequency_from'];
                        $frequency_to = $pairConfig['settings']['frequency_to'];
                        
                        $this->logger->log("!!!!! BotRunner: Оновлена конфігурація для {$this->pair}: frequency_from={$frequency_from}, frequency_to={$frequency_to}");
                        $this->logger->log("!!!!! BotRunner: Повна оновлена конфігурація для бота: " . json_encode($pairConfig));
                        
                        // НОВА ЛОГІКА: Оновлення конфігурації існуючого бота
                        $this->logger->log("!!!!! BotRunner: Оновлення конфігурації існуючого бота");
                        
                        // Очищаємо всі ордери перед оновленням конфігурації
                        $this->logger->log("!!!!! BotRunner: Очищення всіх ордерів перед оновленням конфігурації");
                        await($this->bot->clearAllOrders());
                        
                        // Оновлюємо конфігурацію бота
                        $this->logger->log("!!!!! BotRunner: Застосування нової конфігурації до бота");
                        $this->bot->updateConfig($pairConfig);
                        
                        // Ініціалізуємо бота з новою конфігурацією
                        $this->logger->log("!!!!! BotRunner: Повторна ініціалізація бота з оновленою конфігурацією");
                        await($this->bot->initialize());
                        $this->logger->log("!!!!! BotRunner: Ініціалізацію бота з оновленою конфігурацією завершено");
                        
                        // Оновлюємо хеш конфігурації
                        $this->lastPairConfigHash = $currentPairConfigHash;
                    } else {
                        $this->logger->info("!!!!! BotRunner: Конфігурація для пари {$this->pair} не змінилася");
                    }
                    
                    $this->logger->info("!!!!! BotRunner: Запуск одного циклу бота для пари {$this->pair}");
                    try {
                        // Correctly call runSingleCycle and await its promise
                        $cyclePromise = $this->bot->runSingleCycle();
                        await($cyclePromise);
                        $this->logger->info("[{$this->pair}] Cycle completed successfully.");
                    } catch (\Throwable $e) {
                        $this->logger->error("[{$this->pair}] Error during trading cycle: " . $e->getMessage() . "\nStack trace:\n" . $e->getTraceAsString());
                        // Optionally return a rejected promise here or handle the error differently
                        // For now, just log and continue to the next iteration
                    }
                    
                    $this->logger->log("!!!!! BotRunner: Цикл бота для пари {$this->pair} завершено");
                    
                    // Forcibly getting the latest configuration before the delay
                    $this->logger->log("!!!!! BotRunner: Отримання оновленої конфігурації перед затримкою");
                    Config::reloadConfig();
                    $pairConfig = Config::getPairConfig($this->pair);
                    
                    if ($pairConfig === null) {
                        $this->logger->error("!!!!! BotRunner: Конфігурація для пари {$this->pair} не знайдена перед затримкою");
                        break;
                    }
                    
                    // Delay between cycles (in seconds)
                    $frequency_from = $pairConfig['settings']['frequency_from'];
                    $frequency_to = $pairConfig['settings']['frequency_to'];
                    
                    // If both frequencies are 0, skip the delay
                    $delaySeconds = 0.0; // Use float for asyncSleep
                    if (!($frequency_from === 0 && $frequency_to === 0)) {
                        $minDelay = max(0, (int)$frequency_from);
                        $maxDelay = max($minDelay, (int)$frequency_to);
                        // Use microseconds for random calculation then convert to float seconds
                        $delayMicroseconds = mt_rand($minDelay * 1000000, $maxDelay * 1000000);
                        $delaySeconds = $delayMicroseconds / 1000000.0;
                    }
                    
                    $this->logger->log(sprintf("!!!!! BotRunner: Бот для пари {$this->pair} очікує %.6f секунд до наступного циклу", $delaySeconds));
                    
                    // Splitting the delay into short intervals to react faster to changes
                    $shortInterval = 1.0; // Use float for asyncSleep
                    $remainingDelay = $delaySeconds;
                    
                    while ($remainingDelay > 0 && !$this->terminate) {
                        // Use float comparison and asyncSleep
                        $sleepTime = min($shortInterval, $remainingDelay); 
                        await (\React\Promise\Timer\sleep($sleepTime));
                        $remainingDelay -= $sleepTime;
                        
                        // Configuration check logic remains the same
                        $this->logger->log(sprintf("!!!!! BotRunner: Перевірка змін конфігурації під час затримки, залишилось %.6f сек.", $remainingDelay));
                        Config::reloadConfig();
                        
                        $enabledPairs = Config::getEnabledPairs();
                        if (!in_array($this->pair, $enabledPairs)) {
                            $this->logger->log("!!!!! BotRunner: Пара {$this->pair} деактивована під час очікування, зупиняємо бота");
                            break 2; 
                        }
                        
                        $currentPairConfig = Config::getPairConfig($this->pair);
                        if ($currentPairConfig !== null) {
                            $newPairConfigHash = md5(json_encode($currentPairConfig));
                            if ($newPairConfigHash !== $this->lastPairConfigHash) {
                                $this->logger->log("!!!!! BotRunner: Виявлено зміни конфігурації пари під час очікування, перериваємо очікування");
                                $this->logger->log("!!!!! BotRunner: Новий хеш: {$newPairConfigHash}, старий: {$this->lastPairConfigHash}");
                                break; // Exit the inner delay loop only
                            }
                        }
                    }
                    
                    // Process any pending signals
                    pcntl_signal_dispatch();

                } catch (\Throwable $e) { // Catch Throwable for broader error handling
                    $this->logger->error("!!!!! BotRunner: Помилка під час виконання циклу бота для пари {$this->pair}: " . $e->getMessage());
                    $this->logger->logStackTrace("!!!!! BotRunner: Stack trace помилки циклу: "); // Use logStackTrace helper if available
                    
                    // Sleeping for a short period before the next cycle in case of an error - Use asyncSleep
                    $this->logger->log("!!!!! BotRunner: Затримка 5 секунд після помилки в циклі");
                    await (\React\Promise\Timer\sleep(5.0));
                }
                 // Check for termination signal again after potential sleep/error handling
                pcntl_signal_dispatch();
                if ($this->terminate) {
                     $this->logger->log("!!!!! BotRunner: Отримано сигнал завершення в кінці циклу while для {$this->pair}");
                     break;
                }
            }
            
            $this->logger->log("!!!!! BotRunner: Бот для пари {$this->pair} завершив основний цикл роботи");
            
        } catch (\Throwable $e) { // Catch Throwable
            $this->logger->error("!!!!! BotRunner: Критична помилка при запуску/ініціалізації бота для пари {$this->pair}: " . $e->getMessage());
             $this->logger->logStackTrace("!!!!! BotRunner: Stack trace критичної помилки: "); // Use logStackTrace
            // Ensure loop stops and cleanup happens if possible
             $this->terminate = true; 
           // exit(1); // Avoid abrupt exit if inside async context, let shutdownGracefully handle it.
        } finally {
            // Shutdown gracefully regardless of how the loop ended (normal exit or exception)
             $this->logger->log("!!!!! BotRunner: Виконання блоку finally, виклик shutdownGracefully для {$this->pair}");
             $this->shutdownGracefully();
        }
    }

    /**
     * Run as a process manager that monitors and maintains bot processes
     */
    public function runAsManager(): void
    {
        $this->logger->log("Запуск менеджера процесів ботів");
        
        // Forcibly reloading the configuration
        Config::reloadConfig();
        
        // Чистимо старі PID-файли
        $this->botProcess->cleanupInvalidPidFiles();
        
        // Запускаємо процеси для всіх активних пар
        $this->logger->log("Ініціалізація процесів для всіх активних пар");
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
                    $this->logger->log("Менеджер процесів: перевірка стану процесів...");
                    
                    // Checking if the configuration has changed
                    if (file_exists($this->configFile)) {
                        $configModTime = filemtime($this->configFile);
                        $this->logger->log("Менеджер процесів: час останньої модифікації конфігурації: " . date('Y-m-d H:i:s', $configModTime));
                        
                        // Оновлюємо процеси у відповідності до поточної конфігурації
                        $this->logger->log("Менеджер процесів: оновлення процесів...");
                        $this->botProcess->updateProcesses();
                        $this->logger->log("Менеджер процесів: процеси оновлено");
                    }
                    
                    $this->lastCheckTime = $currentTime;
                }
                
                // Short sleep to prevent CPU overload
                await (\React\Promise\Timer\sleep(1.0));
                
            } catch (Exception $e) {
                $this->logger->error("Менеджер процесів: помилка - " . $e->getMessage());
                $this->logger->error("Менеджер процесів: stack trace - " . $e->getTraceAsString());
                
                // Short sleep in case of an error
                await (\React\Promise\Timer\sleep(5.0));
            }
        }
        
        $this->logger->log("Менеджер процесів завершує роботу");
        $this->shutdownGracefully();
    }
}

// Запуск скрипта
if (count($argv) > 1) {
    $pair = $argv[1];
    $runner = new BotRunner($pair);
    $runner->run();
} 