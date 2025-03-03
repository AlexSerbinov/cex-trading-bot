<?php

declare(strict_types=1);

/**
 * Клас для зберігання та управління даними ботів
 */
class BotStorage
{
    private string $storageFile;
    private array $bots = [];
    private static ?BotStorage $instance = null;

    /**
     * Конструктор
     */
    private function __construct()
    {
        $this->storageFile = __DIR__ . '/../data/bots.json';
        $this->loadBots();
    }

    /**
     * Отримання екземпляру класу (Singleton)
     */
    public static function getInstance(): BotStorage
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Завантаження даних ботів з файлу
     */
    private function loadBots(): void
    {
        if (!file_exists($this->storageFile)) {
            // Створюємо директорію, якщо вона не існує
            $dir = dirname($this->storageFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $this->bots = [];
            $this->saveBots();
        } else {
            $content = file_get_contents($this->storageFile);
            $this->bots = json_decode($content, true) ?: [];
        }
    }

    /**
     * Збереження даних ботів у файл
     */
    private function saveBots(): void
    {
        file_put_contents($this->storageFile, json_encode($this->bots, JSON_PRETTY_PRINT));
    }

    /**
     * Отримання списку всіх ботів
     */
    public function getAllBots(): array
    {
        return array_values($this->bots);
    }

    /**
     * Отримання бота за ID
     */
    public function getBotById(int $id): ?array
    {
        return $this->bots[$id] ?? null;
    }

    /**
     * Додавання нового бота
     */
    public function addBot(array $botData): array
    {
        $id = $this->getNextId();
        $botData['id'] = $id;
        $botData['status'] = 'active';
        $botData['created_at'] = date('Y-m-d H:i:s');
        $botData['active_orders'] = [];
        
        $this->bots[$id] = $botData;
        $this->saveBots();
        
        return $this->bots[$id];
    }

    /**
     * Оновлення даних бота
     */
    public function updateBot(int $id, array $botData): ?array
    {
        if (!isset($this->bots[$id])) {
            return null;
        }
        
        $botData['id'] = $id;
        $botData['status'] = $this->bots[$id]['status'];
        $botData['created_at'] = $this->bots[$id]['created_at'];
        $botData['updated_at'] = date('Y-m-d H:i:s');
        $botData['active_orders'] = $this->bots[$id]['active_orders'] ?? [];
        
        $this->bots[$id] = $botData;
        $this->saveBots();
        
        return $this->bots[$id];
    }

    /**
     * Вимкнення бота
     */
    public function disableBot(int $id): ?array
    {
        if (!isset($this->bots[$id])) {
            return null;
        }
        
        $this->bots[$id]['status'] = 'disabled';
        $this->bots[$id]['updated_at'] = date('Y-m-d H:i:s');
        $this->saveBots();
        
        return $this->bots[$id];
    }

    /**
     * Видалення бота
     */
    public function deleteBot(int $id): bool
    {
        if (!isset($this->bots[$id])) {
            return false;
        }
        
        unset($this->bots[$id]);
        $this->saveBots();
        
        return true;
    }

    /**
     * Отримання наступного ID для нового бота
     */
    private function getNextId(): int
    {
        if (empty($this->bots)) {
            return 1;
        }
        
        return max(array_keys($this->bots)) + 1;
    }

    /**
     * Оновлення активних ордерів бота
     */
    public function updateBotOrders(int $id, array $orders): ?array
    {
        if (!isset($this->bots[$id])) {
            return null;
        }
        
        $this->bots[$id]['active_orders'] = $orders;
        $this->saveBots();
        
        return $this->bots[$id];
    }
} 