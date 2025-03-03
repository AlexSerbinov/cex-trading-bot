<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/BotStorage.php';
require_once __DIR__ . '/../logger.php';

/**
 * Клас для управління ботами через API
 */
class BotManager
{
    private BotStorage $storage;
    private Logger $logger;

    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->storage = BotStorage::getInstance();
        $this->logger = Logger::getInstance();
    }

    /**
     * Отримання списку всіх ботів
     */
    public function getAllBots(): array
    {
        $bots = $this->storage->getAllBots();
        
        // Форматуємо дані для відповіді API
        foreach ($bots as &$bot) {
            if ($bot['status'] === 'active') {
                $bot['frequency_mins'] = "{$bot['frequency_from']}-{$bot['frequency_to']} хв";
                $bot['trade_amount'] = "{$bot['trade_amount_min']}-{$bot['trade_amount_max']}";
            } else {
                $bot['frequency_mins'] = "Неактивний";
                $bot['trade_amount'] = "0.00000000000000000000";
            }
        }
        
        return $bots;
    }

    /**
     * Отримання бота за ID
     */
    public function getBotById(int $id): ?array
    {
        return $this->storage->getBotById($id);
    }

    /**
     * Додавання нового бота
     */
    public function addBot(array $botData): array
    {
        // Валідація даних
        $this->validateBotData($botData);
        
        // Перевіряємо, чи існує бот з такою парою
        $existingBots = $this->storage->getAllBots();
        foreach ($existingBots as $existingBot) {
            if ($existingBot['market'] === $botData['market'] && $existingBot['status'] === 'active') {
                // Бот з такою парою вже існує і активний
                $this->logger->log("Спроба створити дублікат бота для пари {$botData['market']}");
                return $existingBot;
            }
        }
        
        // Додаємо бота в сховище
        $bot = $this->storage->addBot($botData);
        
        // Оновлюємо конфігурацію
        $this->updateConfigForBot($bot);
        
        $this->logger->log("Створено нового бота: ID={$bot['id']}, Пара={$bot['market']}");
        
        return $bot;
    }

    /**
     * Оновлення даних бота
     */
    public function updateBot(int $id, array $botData): ?array
    {
        // Перевіряємо, чи існує бот
        $existingBot = $this->storage->getBotById($id);
        if (!$existingBot) {
            return null;
        }
        
        // Валідація даних
        $this->validateBotData($botData);
        
        // Оновлюємо дані бота
        $bot = $this->storage->updateBot($id, $botData);
        
        // Оновлюємо конфігурацію в Config
        $this->updateConfigForBot($bot);
        
        $this->logger->log("Оновлено бота: ID={$bot['id']}, Пара={$bot['market']}");
        
        return $bot;
    }

    /**
     * Вимкнення бота
     */
    public function disableBot(int $id): ?array
    {
        // Перевіряємо, чи існує бот
        $existingBot = $this->storage->getBotById($id);
        if (!$existingBot) {
            return null;
        }
        
        // Вимикаємо бота
        $bot = $this->storage->disableBot($id);
        
        // Оновлюємо конфігурацію в Config (вимикаємо пару)
        $this->disablePairInConfig($bot['market']);
        
        $this->logger->log("Вимкнено бота: ID={$bot['id']}, Пара={$bot['market']}");
        
        return $bot;
    }

    /**
     * Видалення бота
     */
    public function deleteBot(int $id): bool
    {
        // Перевіряємо, чи існує бот
        $existingBot = $this->storage->getBotById($id);
        if (!$existingBot) {
            return false;
        }
        
        // Вимикаємо пару в конфігурації
        $this->disablePairInConfig($existingBot['market']);
        
        // Видаляємо бота
        $result = $this->storage->deleteBot($id);
        
        if ($result) {
            $this->logger->log("Видалено бота: ID={$id}, Пара={$existingBot['market']}");
        }
        
        return $result;
    }

    /**
     * Валідація даних бота
     */
    private function validateBotData(array $botData): void
    {
        $requiredFields = [
            'market', 'trade_amount_min', 'trade_amount_max', 
            'frequency_from', 'frequency_to', 'price_factor', 'exchange'
        ];
        
        foreach ($requiredFields as $field) {
            if (!isset($botData[$field])) {
                throw new InvalidArgumentException("Відсутнє обов'язкове поле: {$field}");
            }
        }
        
        // Перевірка коректності значень
        if ($botData['trade_amount_min'] <= 0 || $botData['trade_amount_max'] <= 0) {
            throw new InvalidArgumentException("Обсяг торгівлі повинен бути більше нуля");
        }
        
        if ($botData['trade_amount_min'] > $botData['trade_amount_max']) {
            throw new InvalidArgumentException("Мінімальний обсяг не може бути більшим за максимальний");
        }
        
        if ($botData['frequency_from'] <= 0 || $botData['frequency_to'] <= 0) {
            throw new InvalidArgumentException("Частота оновлення повинна бути більше нуля");
        }
        
        if ($botData['frequency_from'] > $botData['frequency_to']) {
            throw new InvalidArgumentException("Мінімальна частота не може бути більшою за максимальну");
        }
    }

    /**
     * Оновлення конфігурації для бота
     */
    private function updateConfigForBot(array $bot): void
    {
        // Створюємо конфігурацію для пари
        $config = [
            'external_api_url' => $this->getApiUrlForExchange($bot['exchange'], $bot['market']),
            'bot_balance' => $bot['trade_amount_max'],
            'min_orders' => 15,
            'max_orders' => 17,
            'price_deviation_percent' => $bot['price_factor'],
            'enabled' => $bot['status'] === 'active',
        ];
        
        // Оновлюємо конфігурацію через Config
        Config::updateDynamicConfig($bot['market'], $config);
        
        // Додаємо запис у лог про оновлення конфігурації
        $this->logger->log("Оновлено конфігурацію для пари {$bot['market']}");
    }

    /**
     * Вимкнення пари в конфігурації
     */
    private function disablePairInConfig(string $market): void
    {
        Config::disablePair($market);
    }

    /**
     * Отримання URL API для біржі та пари
     */
    private function getApiUrlForExchange(string $exchange, string $market): string
    {
        // Замінюємо підкреслення на порожній рядок для формату Kraken
        $marketSymbol = str_replace('_', '', $market);
        
        switch ($exchange) {
            case 'kraken':
                return "https://api.kraken.com/0/public/Depth?pair={$marketSymbol}";
            case 'binance':
                return "https://api.binance.com/api/v3/depth?symbol={$marketSymbol}";
            case 'bitfinex':
                return "https://api-pub.bitfinex.com/v2/book/t{$marketSymbol}/P0";
            default:
                return "https://api.kraken.com/0/public/Depth?pair={$marketSymbol}";
        }
    }
} 