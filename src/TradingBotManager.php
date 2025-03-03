<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tradingBotMain.php';
require_once __DIR__ . '/logger.php';

/**
 * Клас для управління кількома ботами для різних пар
 */
class TradingBotManager
{
    private Logger $logger;
    private array $bots = [];
    private array $lastRunTime = [];
    private int $rotationInterval = 1000; // мс між запусками ботів
    private array $dynamicConfig = [];

    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->loadDynamicConfig();
        $this->initializeBots();
    }

    /**
     * Завантаження динамічної конфігурації
     */
    private function loadDynamicConfig(): void
    {
        // Тепер ми не потребуємо цього методу, оскільки Config сам завантажує динамічну конфігурацію
        // Але залишаємо його для сумісності
    }

    /**
     * Ініціалізація ботів для всіх активних пар
     */
    private function initializeBots(): void
    {
        // Отримуємо список активних пар з Config
        $enabledPairs = Config::getEnabledPairs();
        
        $this->logger->log(
            sprintf('Ініціалізація ботів для %d пар: %s', count($enabledPairs), implode(', ', $enabledPairs)),
        );

        foreach ($enabledPairs as $pair) {
            // Отримуємо конфігурацію для пари з Config
            $pairConfig = Config::getPairConfig($pair);
            
            // Створюємо бота з конфігурацією
            $this->bots[$pair] = new TradingBot($pair, $pairConfig);
            $this->lastRunTime[$pair] = 0;
            $this->logger->log(sprintf('Бот для пари %s ініціалізовано', $pair));
        }
    }

    /**
     * Запуск всіх ботів у режимі мультиплексування
     */
    public function runAllBots(): void
    {
        $this->logger->log('Запуск всіх ботів у режимі мультиплексування');

        // Ініціалізація всіх ботів
        foreach ($this->bots as $pair => $bot) {
            $bot->initialize();
            $this->logger->log(sprintf('Бот для пари %s ініціалізовано', $pair));
            usleep(500000); // Пауза 0.5 секунди між ініціалізаціями
        }

        // Основний цикл
        while (true) {
            // Перевіряємо, чи є оновлення в конфігурації
            $this->checkConfigUpdates();
            
            $currentTime = $this->getCurrentTimeMs();

            foreach ($this->bots as $pair => $bot) {
                // Перевіряємо, чи пройшов достатній час з останнього запуску
                if ($currentTime - $this->lastRunTime[$pair] >= $this->rotationInterval) {
                    $this->logger->log(sprintf('Запуск циклу для пари %s', $pair));
                    $bot->runSingleCycle();
                    $this->lastRunTime[$pair] = $currentTime;
                }
            }

            usleep(100000); // Пауза 100 мс між перевірками
        }
    }
    
    /**
     * Перевірка оновлень конфігурації
     */
    private function checkConfigUpdates(): void
    {
        // Отримуємо список активних пар з Config
        $enabledPairs = Config::getEnabledPairs();
        
        // Зупиняємо неактивні боти
        foreach (array_keys($this->bots) as $pair) {
            if (!in_array($pair, $enabledPairs)) {
                $this->logger->log(sprintf('Зупинка бота для пари %s', $pair));
                unset($this->bots[$pair]);
                unset($this->lastRunTime[$pair]);
            }
        }
        
        // Додаємо нові боти
        foreach ($enabledPairs as $pair) {
            if (!isset($this->bots[$pair])) {
                $this->logger->log(sprintf('Додавання нового бота для пари %s', $pair));
                $pairConfig = Config::getPairConfig($pair);
                $this->bots[$pair] = new TradingBot($pair, $pairConfig);
                $this->lastRunTime[$pair] = 0;
                $this->bots[$pair]->initialize();
            }
        }
    }

    /**
     * Отримати поточний час у мілісекундах
     *
     * @return int Поточний час у мс
     */
    private function getCurrentTimeMs(): int
    {
        return (int) (microtime(true) * 1000);
    }
}

// Запуск менеджера ботів, якщо файл викликано напряму
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $manager = new TradingBotManager();
    $manager->runAllBots();
}
