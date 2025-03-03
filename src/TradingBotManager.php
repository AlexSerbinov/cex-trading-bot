<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tradingBotMain.php';
require_once __DIR__ . '/Logger.php';

/**
 * Клас для управління кількома ботами для різних пар
 */
class TradingBotManager
{
    private Logger $logger;
    private array $bots = [];
    private array $lastRunTime = [];
    private int $rotationInterval = 1000; // мс між запусками ботів

    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->logger = new Logger();
        $this->initializeBots();
    }

    /**
     * Ініціалізація ботів для всіх активних пар
     */
    private function initializeBots(): void
    {
        $enabledPairs = Config::getEnabledPairs();
        $this->logger->log(
            sprintf('Ініціалізація ботів для %d пар: %s', count($enabledPairs), implode(', ', $enabledPairs)),
        );

        foreach ($enabledPairs as $pair) {
            $this->bots[$pair] = new TradingBot($pair);
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
     * Отримати поточний час у мілісекундах
     *
     * @return int Поточний час у мс
     */
    private function getCurrentTimeMs(): int
    {
        return (int) (microtime(true) * 1000);
    }
}

// Запуск менеджера ботів
$manager = new TradingBotManager();
$manager->runAllBots();
