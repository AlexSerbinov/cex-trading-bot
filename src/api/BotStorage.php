<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../../config/config.php';

/**
 * Class for storing and managing bot data
 */
class BotStorage
{
    private string $storageFile;
    private array $bots = [];
    private static ?BotStorage $instance = null;
    private Logger $logger;

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->storageFile = __DIR__ . '/../../config/bots_config.json';
        $this->logger = Logger::getInstance();
        $this->loadBots();
    }

    /**
     * Getting the instance of the class (Singleton)
     */
    public static function getInstance(): BotStorage
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Loading bot data from file
     */
    private function loadBots(): void
    {
        if (!file_exists($this->storageFile)) {
            $this->logger->error("Config file not found: " . $this->storageFile);
            $this->bots = [];
            return;
        }
        
        $content = file_get_contents($this->storageFile);
        if ($content === false) {
            $this->logger->error("Failed to read config file: " . $this->storageFile);
            $this->bots = [];
            return;
        }
        
        $data = json_decode($content, true);
        if ($data === null) {
            $this->logger->error("Failed to decode JSON from config file: " . json_last_error_msg());
            $this->bots = [];
            return;
        }
        
        $this->bots = $data;
        $this->logger->log("Loaded bots from file: " . count($this->bots) . " bots");
    }

    /**
     * Save bots data to file
     */
    private function saveBots(): void
    {
        $json = json_encode($this->bots, JSON_PRETTY_PRINT);
        $this->logger->log("Saving bots to file: " . $json);
        
        if ($json === false) {
            $this->logger->error("Failed to encode bots to JSON: " . json_last_error_msg());
            return;
        }
        
        // Saving to a file
        $result = file_put_contents($this->storageFile, $json);
        
        if ($result === false) {
            $this->logger->error("Failed to save bots to file: " . $this->storageFile);
        } else {
            $this->logger->log("Successfully saved bots to file: " . $this->storageFile);
        }
        
        // Force reload the configuration in Config
        Config::reloadConfig();
    }

    /**
     * Getting all bots
     */
    public function getAllBots(): array
    {
        // Get bots from configuration
        $bots = Config::getAllBots();
        
        // Enrich bot data with additional fields
        foreach ($bots as &$bot) {
            $pair = $bot['market'];
            
            // Add bot settings
            $bot['settings'] = [
                'trade_amount_min' =>  ($bot['settings']['trade_amount_min']),
                'trade_amount_max' =>  ($bot['settings']['trade_amount_max']),
                'frequency_from' =>  ($bot['settings']['frequency_from']),
                'frequency_to' =>  ($bot['settings']['frequency_to']),
                'price_factor' =>  ($bot['settings']['price_factor']),
                'market_gap' =>  ($bot['settings']['market_gap']),
                'min_orders' =>  ($bot['settings']['min_orders']),
                'max_orders' =>  ($bot['settings']['max_orders']),
                'market_maker_order_probability' =>  ($bot['settings']['market_maker_order_probability'])
            ];
        }
        
        return $bots;
    }

    /**
     * Getting a bot by ID
     */
    public function getBotById(int $id): ?array
    {
        // Get bot from configuration
        $bot = Config::getBotById($id);
        
        if (!$bot) {
            return null;
        }
        
        $pair = $bot['market'];
        
        // Add bot settings
        $bot['settings'] = [
            'trade_amount_min' => ($bot['settings']['trade_amount_min']),
            'trade_amount_max' => ($bot['settings']['trade_amount_max']),
            'frequency_from' => ($bot['settings']['frequency_from']),
            'frequency_to' => ($bot['settings']['frequency_to']),
            'price_factor' => ($bot['settings']['price_factor']),
            'market_gap' => ($bot['settings']['market_gap']),
            'min_orders' => ($bot['settings']['min_orders']),
            'max_orders' => ($bot['settings']['max_orders']),
            'market_maker_order_probability' => ($bot['settings']['market_maker_order_probability'])
        ];
        
        return $bot;
    }

    /**
     * Add new bot
     */
    public function addBot(array $bot): ?array
    {
        // Перевірка, що $bot не null
        if ($bot === null) {
            $this->logger->error("Cannot add null bot");
            return null;
        }
        
        // Validate required fields
        if (!isset($bot['market']) || !isset($bot['exchange'])) {
            return null;
        }
        
        // Перевірка на порожню пару
        if (empty($bot['market'])) {
            $this->logger->error("Cannot add bot with empty market");
            return null;
        }
        
        // Додаємо ID, якщо його немає
        if (!isset($bot['id'])) {
            $bot['id'] = $this->getNextId();
        }
        
        // Додаємо дати, якщо їх немає
        if (!isset($bot['created_at'])) {
            $bot['created_at'] = date('Y-m-d H:i:s');
        }
        if (!isset($bot['updated_at'])) {
            $bot['updated_at'] = date('Y-m-d H:i:s');
        }
        
        // Додаємо статус, якщо його немає
        if (!isset($bot['isActive'])) {
            $bot['isActive'] = true;
        }
        
        // Перевіряємо наявність налаштувань
        if (!isset($bot['settings']) && (isset($bot['trade_amount_min']) || isset($bot['trade_amount_max']) || 
            isset($bot['frequency_from']) || isset($bot['frequency_to']) || 
            isset($bot['price_factor']) || isset($bot['market_gap']))) {
            
            $bot['settings'] = [
                'min_orders' => $bot['min_orders'],
                'max_orders' => $bot['max_orders'],
                'trade_amount_min' => $bot['trade_amount_min'],
                'trade_amount_max' => $bot['trade_amount_max'],
                'frequency_from' => $bot['frequency_from'],
                'frequency_to' => $bot['frequency_to'],
                'price_factor' => $bot['price_factor'],
                'market_gap' => $bot['market_gap'],
                'market_maker_order_probability' => $bot['settings']['market_maker_order_probability']
            ];
        }
        
        // Check if the bot already exists
        if (isset($this->bots[$bot['market']])) {
            return null;
        }
        
        // Add the bot to storage
        $this->logger->log("Adding bot with settings: " . json_encode($bot['settings'] ?? []));
        
        // Transform to config format
        $configBot = $this->transformToConfigFormat($bot);
        
        if ($configBot === null) {
            $this->logger->error("Failed to transform bot to config format");
            return null;
        }
        
        // Add to the bots array
        $this->bots[$bot['market']] = $configBot;
        
        // Save to file
        $this->saveBots();
        
        return $this->transformToApiFormat($configBot);
    }

    /**
     * Update bot data
     */
    public function updateBot(int $id, array $botData): ?array
    {
        $this->logger->log("Updating bot with ID {$id}: " . json_encode($botData));
        
        // Reload data from file to get the latest
        $this->loadBots();
        
        // Find the bot by ID
        $foundPair = null;
        foreach ($this->bots as $pair => $bot) {
            if (isset($bot['id']) && $bot['id'] === $id) {
                $foundPair = $pair;
                break;
            }
        }
        
        if ($foundPair === null) {
            $this->logger->error("Bot with ID {$id} not found");
            return null;
        }
        
        // Зберігаємо оригінальний market
        $market = $botData['market'] ?? $foundPair;
        
        // Перевіряємо, чи змінився market
        if ($market !== $foundPair) {
            // Якщо market змінився, перевіряємо, чи не існує вже бот з таким market
            if (isset($this->bots[$market])) {
                $this->logger->error("Bot with market {$market} already exists");
                return null;
            }
            
            // Копіюємо бота зі старим market
            $bot = $this->bots[$foundPair];
            
            // Оновлюємо дані бота
            foreach ($botData as $key => $value) {
                if ($key !== 'id') { // ID не змінюємо
                    $bot[$key] = $value;
                }
            }
            
            // Видаляємо старого бота
            unset($this->bots[$foundPair]);
            
            // Додаємо нового бота з новим market
            $this->bots[$market] = $bot;
        } else {
            // Якщо market не змінився, просто оновлюємо дані бота
            foreach ($botData as $key => $value) {
                if ($key !== 'id') { // ID не змінюємо
                    $this->bots[$foundPair][$key] = $value;
                }
            }
        }
        
        // Зберігаємо дані
        $this->saveBots();
        
        // Повертаємо оновленого бота
        $updatedBot = $this->bots[$market];
        $updatedBot['market'] = $market;
        
        return $updatedBot;
    }

    /**
     * Disable bot
     */
    public function disableBot(int $id): ?array
    {
        // Reload data from file to get the latest
        $this->loadBots();
        
        if (!isset($this->bots[$id])) {
            return null;
        }
        
        $this->bots[$id]['isActive'] = false;
        $this->bots[$id]['updated_at'] = date('Y-m-d H:i:s');
        $this->saveBots();
        
        $this->logger->log("Disabled the bot: ID={$id}, Pair={$this->bots[$id]['market']}");
        return $this->bots[$id];
    }

    /**
     * Delete bot
     */
    public function deleteBot(int $id): bool
    {
        $this->logger->log("Deleting bot with ID {$id}");
        
        // Reload data from file to get the latest
        $this->loadBots();
        
        // Find the bot by ID
        $foundPair = null;
        foreach ($this->bots as $pair => $bot) {
            if (isset($bot['id']) && $bot['id'] === $id) {
                $foundPair = $pair;
                break;
            }
        }
        
        if ($foundPair === null) {
            $this->logger->error("Bot with ID {$id} not found for deletion");
            return false;
        }
        
        // Delete the bot
        unset($this->bots[$foundPair]);
        
        // Save the changes
        $this->saveBots();
        
        $this->logger->log("Bot with ID {$id} deleted successfully");
        return true;
    }

    /**
     * Reindexing the bot IDs
     */
    private function reindexBots(): void
    {
        if (empty($this->bots)) {
            return;
        }
        
        // Sort bots by ID
        ksort($this->bots);
        
        // Create a new array with reindexed IDs
        $newBots = [];
        $newId = 1;
        
        foreach ($this->bots as $oldId => $bot) {
            $bot['id'] = $newId;
            $newBots[$newId] = $bot;
            $newId++;
        }
        
        $this->bots = $newBots;
        $this->logger->log("Reindexed the bot IDs after deletion");
    }

    /**
     * Get next ID for new bot
     */
    public function getNextId(): int
    {
        if (empty($this->bots)) {
            return 1;
        }
        
        // Переконуємося, що ключі масиву - це числа
        $ids = array_map(function($key) {
            return is_numeric($key) ? (int)$key : 0;
        }, array_keys($this->bots));
        
        // Також перевіряємо ID всередині об'єктів ботів
        foreach ($this->bots as $bot) {
            if (isset($bot['id']) && is_numeric($bot['id'])) {
                $ids[] = (int)$bot['id'];
            }
        }
        
        // Якщо масив порожній після фільтрації, повертаємо 1
        if (empty($ids)) {
            return 1;
        }
        
        return max($ids) + 1;
    }

    private function transformBotConfig($config, $pair, $id)
    {
        // Перевірка на null або відсутність необхідних полів
        if ($config === null || !isset($pair) || !isset($id)) {
            $this->logger->error("Invalid parameters for transformBotConfig");
            return null;
        }
        
        $this->logger->log("transformBotConfig input: " . json_encode([
            'config' => $config,
            'pair' => $pair,
            'id' => $id
        ]));
        
        // Перевірка наявності налаштувань
        $settings = $config['settings'] ?? [];
        $this->logger->log("Settings from config: " . json_encode($settings));
        
        $result = [
            'id' => $id,
            'market' => $pair,
            'exchange' => $config['exchange'] ?? 'binance',
            'isActive' => $config['isActive'] ?? false,
            'created_at' => $config['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $config['updated_at'] ?? date('Y-m-d H:i:s'),
            'settings' => $settings,
            'trade_amount_min' => $settings['trade_amount_min'],
            'trade_amount_max' => $settings['trade_amount_max'],
            'frequency_from' => $settings['frequency_from'],
            'frequency_to' => $settings['frequency_to'],
            'price_deviation_percent' => $settings['price_factor'],
            'market_gap' => $settings['market_gap'],
            'min_orders' => $settings['min_orders'],
            'max_orders' => $settings['max_orders'],
            'market_maker_order_probability' => $settings['market_maker_order_probability']
        ];
        
        $this->logger->log("transformBotConfig result: " . json_encode($result));
        return $result;
    }

    private function transformToConfigFormat($bot)
    {
        $this->logger->log("Transforming bot to config format: " . json_encode($bot));
        
        // Перевірка наявності необхідних полів
        if (!isset($bot['market']) || !isset($bot['exchange'])) {
            $this->logger->error("Missing required fields in bot data");
            return null;
        }
        
        // Отримання ID (або генерація нового, якщо відсутній)
        $id = $bot['id'] ?? $this->getNextId();
        
        // Базові налаштування з параметрів або значень за замовчуванням
        $settings = $bot['settings'] ?? [];
        
        // Створюємо конфігурацію тільки з налаштуваннями в settings
        return [
            'id' => $id,
            'market' => $bot['market'],
            'exchange' => $bot['exchange'],
            'isActive' => $bot['isActive'] ?? true,
            'created_at' => $bot['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $bot['updated_at'] ?? date('Y-m-d H:i:s'),
            'settings' => [
                'min_orders' => $settings['min_orders'],
                'max_orders' => $settings['max_orders'],
                'trade_amount_min' => $settings['trade_amount_min'],
                'trade_amount_max' => $settings['trade_amount_max'],
                'frequency_from' => $settings['frequency_from'],
                'frequency_to' => $settings['frequency_to'],
                'price_factor' => $settings['price_factor'],
                'market_gap' => $settings['market_gap'],
                'market_maker_order_probability' => $settings['market_maker_order_probability']
            ]
        ];
    }

    private function transformToApiFormat($bot)
    {
        // Перевірка на null
        if ($bot === null) {
            return null;
        }
        
        // Перевірка наявності необхідних полів
        if (!isset($bot['id']) || !isset($bot['market'])) {
            $this->logger->error("Missing required fields in bot data for API format");
            return null;
        }
        
        return [
            'id' => $bot['id'],
            'market' => $bot['market'],
            'exchange' => $bot['exchange'],
            'settings' => $bot['settings'] ?? [
                'min_orders' => $bot['min_orders'],
                'max_orders' => $bot['max_orders'],
                'trade_amount_min' => $bot['trade_amount_min'],
                'trade_amount_max' => $bot['trade_amount_max'],
                'frequency_from' => $bot['frequency_from'],
                'frequency_to' => $bot['frequency_to'],
                'price_factor' => $bot['price_deviation_percent'],
                'market_gap' => $bot['market_gap'],
                'market_maker_order_probability' => $bot['market_maker_order_probability']
            ]
        ];
    }

    /**
     * Getting a bot by market pair
     */
    public function getBotByMarket(string $market): ?array
    {
        // Reload data from file to get the latest
        $this->loadBots();
        
        foreach ($this->bots as $pair => $bot) {
            if ($pair === $market) {
                $bot['market'] = $pair;
                return $bot;
            }
        }
        
        return null;
    }
} 