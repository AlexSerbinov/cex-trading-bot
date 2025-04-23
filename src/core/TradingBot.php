<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';  
require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/ExchangeManager.php';
require_once __DIR__ . '/MarketMakerActions.php';

// Додаємо use для React\Async
use function React\Async\await;
use React\Promise\PromiseInterface; // Можливо, вже є
use function React\Promise\all;
use function React\Promise\resolve;
use function React\Async\async;
use function React\Promise\reject;

/**
 * TradingBot - an automated bot for simulating trades on a cryptocurrency exchange.
 */
class TradingBot
{
    private string $pair;
    private array $pairConfig;
    private Logger $logger;
    private ExchangeManager $exchangeManager;
    private ApiClient $apiClient;
    private array $openOrders = [];
    private bool $isInitialized = false;
    private MarketMakerActions $marketMakerActions;
    public bool $lastActionWasSell = false;

    /**
     * Constructor for TradingBot.
     *
     * @param string $pair Trading pair for this bot instance
     * @param array|null $dynamicConfig Dynamic configuration for the pair
     */
    public function __construct(string $pair, ?array $dynamicConfig = null)
    {
        $this->pair = $pair;
        
        // Отримуємо конфігурацію для пари
        $this->pairConfig = $dynamicConfig ?? Config::getPairConfig($pair);
        
        $this->apiClient = new ApiClient();
        $this->logger = Logger::getInstance();
        $this->exchangeManager = ExchangeManager::getInstance();
        
        $this->logger->log("Created a bot for the pair {$pair}");
        
        // Логування завантаженої конфігурації для діагностики
        $settings = $this->pairConfig['settings'];
        $this->logger->log(sprintf(
            'Settings for %s: min_orders=%s, max_orders=%s, trade_amount_min=%s, trade_amount_max=%s, frequency_from=%s, frequency_to=%s, probability=%s, market_gap=%s, price_factor=%s',
            $pair,
            $settings['min_orders'],
            $settings['max_orders'],
            $settings['trade_amount_min'],
            $settings['trade_amount_max'],
            $settings['frequency_from'],
            $settings['frequency_to'],
            $settings['market_maker_order_probability'],
            $settings['market_gap'],
            $settings['price_factor']
        ));

        // Ініціалізуємо клас MarketMakerActions
        $this->marketMakerActions = new MarketMakerActions(
            $this,
            $this->logger,
            $this->pair,
            $this->pairConfig
        );
    }

    /**
     * Updates the bot configuration without restarting it.
     * 
     * @param array $newConfig New configuration for the bot
     * @return void
     */
    public function updateConfig(array $newConfig): void
    {
        $this->logger->log("!!!!! TradingBot: Оновлення конфігурації для пари {$this->pair}");
        
        // Зберігаємо старі значення для логування
        $oldFrequencyFrom = $this->pairConfig['settings']['frequency_from'] ?? 'Не встановлено';
        $oldFrequencyTo = $this->pairConfig['settings']['frequency_to'] ?? 'Не встановлено';
        $oldOrderCount = $this->pairConfig['settings']['order_count'] ?? 'Не встановлено';
        $oldVolume = $this->pairConfig['settings']['volume'] ?? 'Не встановлено';
        
        // Оновлюємо конфігурацію
        $this->pairConfig = $newConfig;
        
        // Логуємо зміни в основних параметрах
        $newFrequencyFrom = $this->pairConfig['settings']['frequency_from'] ?? 'Не встановлено';
        $newFrequencyTo = $this->pairConfig['settings']['frequency_to'] ?? 'Не встановлено';
        $newOrderCount = $this->pairConfig['settings']['order_count'] ?? 'Не встановлено';
        $newVolume = $this->pairConfig['settings']['volume'] ?? 'Не встановлено';
        
        $this->logger->log("!!!!! TradingBot: Зміни в конфігурації для пари {$this->pair}:");
        $this->logger->log("!!!!! TradingBot: - Частота від: {$oldFrequencyFrom} -> {$newFrequencyFrom}");
        $this->logger->log("!!!!! TradingBot: - Частота до: {$oldFrequencyTo} -> {$newFrequencyTo}");
        $this->logger->log("!!!!! TradingBot: - Кількість ордерів: {$oldOrderCount} -> {$newOrderCount}");
        $this->logger->log("!!!!! TradingBot: - Обсяг: {$oldVolume} -> {$newVolume}");
        
        // Оновлення стратегії, якщо вона змінилася
        if (isset($newConfig['settings']['strategy']) && isset($this->pairConfig['settings']['strategy'])) {
            $oldStrategy = $this->marketMakerActions ? get_class($this->marketMakerActions) : 'Не встановлено';
            $this->logger->log("!!!!! TradingBot: - Стратегія: {$oldStrategy} -> буде оновлено під час ініціалізації");
        }
        
        $this->logger->log("!!!!! TradingBot: Конфігурація для пари {$this->pair} успішно оновлена");
    }

    /**
     * Initializes the bot asynchronously by clearing orders and setting up initial order book.
     * Returns a Promise that resolves when initialization is complete.
     */
    public function initialize(): PromiseInterface
    {
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Початок ініціалізації бота...");
        
        return async(function () {
        if ($this->isInitialized) {
            $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Бот вже ініціалізований, пропускаємо ініціалізацію");
                return \React\Promise\resolve(true);
        }
        
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Бот не ініціалізований, виконуємо ініціалізацію...");
        
        // Очищення всіх ордерів
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Очищення всіх ордерів");
        $this->clearAllOrders();
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Всі ордери очищені");
        
        // Отримання ордербука із зовнішнього API
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Отримання ордербука із зовнішнього API");
        $orderBook = $this->getExternalOrderBook();
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Отримано ордербук: " . count($orderBook['bids']) . " bid, " . count($orderBook['asks']) . " ask");
        
        // Ініціалізація початкових ордерів
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Ініціалізація ордербука");
        $this->initializeOrderBook($orderBook);
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Ордербук ініціалізовано");
        
        $this->isInitialized = true;
            $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Ініціалізацію бота успішно завершено (async)");
            return true;
        })()->catch(function (\Throwable $e) {
            // Catch errors specifically from the async initialization process
            $this->logger->error("!!!!! TradingBot::initialize(): [{$this->pair}] Async Initialization failed: " . $e->getMessage());
            // Додаємо логування стек трейсу для відстеження джерела помилки
            $this->logger->logStackTrace("[{$this->pair}] Stack trace for initialize exception:");
            // Return a rejected promise
            return \React\Promise\reject($e);
        });
    }

    /**
     * Runs a single trading cycle.
     */
    public function runSingleCycle(): void
    {
        if (!$this->isInitialized) {
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Бот не ініціалізований, пропускаємо цикл");
            return;
        }
        
        $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Початок торгового циклу");
        
        try {
            // Отримуємо ордербук
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Запит на отримання ордербука");
            $orderBook = $this->getExternalOrderBook();
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Отримано ордербук: " . count($orderBook['bids']) . " bid, " . count($orderBook['asks']) . " ask");
            
            // Отримуємо поточні ордери
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Запит на отримання поточних ордерів");
            $pendingOrders = $this->getPendingOrders();
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Отримано поточних ордерів: " . count($pendingOrders));
            
            // Підтримка ордерів
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Початок підтримки ордерів");
            $this->maintainOrders($orderBook, $pendingOrders);
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Підтримка ордерів завершена");

            // Надсилання серцебиття до Dead Watcher, якщо увімкнено
            if (Config::isDeadWatcherEnabled()) {
                $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Надсилання серцебиття до Dead Watcher");
                $this->sendHeartbeatToDeadWatcher();
            }

            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Торговий цикл успішно завершено");
        } catch (Exception $e) {
            $this->logger->error("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Помилка в торговому циклі: " . $e->getMessage());
            // Додаємо логування стек трейсу для відстеження джерела помилки
            $this->logger->logStackTrace("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Стек трейс помилки торгового циклу:");
        }
    }

    /**
     * Sends a heartbeat signal to Dead Watcher.
     */
    private function sendHeartbeatToDeadWatcher(): void
    {
        $urls = Config::getDeadWatcherUrls();
        if (empty($urls)) {
            $this->logger->log("[dead_watcher][{$this->pair}] No Dead Watcher URLs configured, skipping heartbeat");
            return;
        }

        $data = [
            'pair' => $this->pair,
            'bot_id' => Config::DEAD_WATCHER_BOT_ID,
            'timestamp' => time()
        ];

        foreach ($urls as $url) {
            try {
                $response = $this->apiClient->post($url, json_encode($data));
                $this->logger->log("[dead_watcher][{$this->pair}] Sent heartbeat to {$url}: " . $response);
            } catch (Exception $e) {
                $this->logger->error("[dead_watcher][{$this->pair}] Failed to send heartbeat to {$url}: " . $e->getMessage());
            }
        }
    }

    /**
     * Runs the bot to simulate trades continuously.
     */
    public function run(): void
    {
        $this->initialize();

        while (true) {
            try {
                $this->runSingleCycle();
                $this->randomDelay(Config::DELAY_RUN_MIN, Config::DELAY_RUN_MAX);
            } catch (Exception $e) {
                $this->logger->error("[{$this->pair}] Error: " . $e->getMessage());
                // Додаємо логування стек трейсу для відстеження джерела помилки
                $this->logger->logStackTrace("[{$this->pair}] Stack trace for main loop error:");
                $this->randomDelay(Config::DELAY_RUN_MIN, Config::DELAY_RUN_MAX);
            }
        }
    }

    /**
     * Fetches the order book from external API with retries.
     *
     * @return array<string, array>
     * @throws RuntimeException If the request fails after 3 retries
     */
    private function getExternalOrderBook(): array
    {
        // Getting the exchange from the configuration
        $exchange = $this->pairConfig['exchange'];
        
        try {
            // Using the ExchangeManager to get the order book
            return $this->exchangeManager->getOrderBook($exchange, $this->pair);
        } catch (Exception $e) {
            $this->logger->error("[{$this->pair}] Unable to get the order book: " . $e->getMessage());
            // Додаємо логування стек трейсу для відстеження джерела помилки
            $this->logger->logStackTrace("[{$this->pair}] Stack trace for order book error:");
            throw new RuntimeException("Error getting order book: " . $e->getMessage());
        }
    }

    /**
     * Fetches the current order book from your exchange.
     *
     * @param int $side Order type: 1 - asks, 2 - bids
     * @return array
     * @throws RuntimeException If the request fails
     */
    private function getCurrentOrderBook(int $side): array
    {
        $body = [
            'method' => 'order.book',
            'params' => [$this->pair, $side, 0, 100],
            'id' => 1,
        ];
        $response = $this->apiClient->post(Config::getTradeServerUrl(), json_encode($body));
        $data = json_decode($response, true);

        if ($data['error'] !== null) {
            $this->logger->log(
                sprintf(
                    '[%s] API response for side=%d: %s',
                    $this->pair,
                    $side,
                    json_encode($data, JSON_PRETTY_PRINT),
                ),
            );
        }

        if (isset($data['result']['orders'])) {
            return $data['result']['orders'];
        }
        $this->logger->error(
            sprintf(
                '[%s] Unexpected response structure for side=%d: %s',
                $this->pair,
                $side,
                json_encode($data, JSON_PRETTY_PRINT),
            ),
        );
        return [];
    }

    /**
     * Fetches the list of open orders for the bot.
     *
     * @return array
     * @throws RuntimeException If the request fails
     */
    private function getPendingOrders(): array
    {
        $body = [
            'method' => 'order.pending',
            'params' => [Config::BOT_USER_ID, $this->pair, 0, 100],
            'id' => 1,
        ];
        $response = $this->apiClient->post(Config::getTradeServerUrl(), json_encode($body));
        $data = json_decode($response, true);

        if ($data['error'] !== null) {
            $this->logger->log(
                sprintf(
                    '[%s] API response for open orders: %s',
                    $this->pair,
                    json_encode($data, JSON_PRETTY_PRINT),
                ),
            );
        }

        $pendingOrders = $data['result']['records'] ?? [];
        
        // Детальне логування стакану
        if (!empty($pendingOrders)) {
            $bids = array_filter($pendingOrders, fn($o) => $o['side'] === 2);
            $asks = array_filter($pendingOrders, fn($o) => $o['side'] === 1);
            
            // Сортуємо ордери за ціною
            usort($bids, fn($a, $b) => (float)$b['price'] - (float)$a['price']); // Біди сортуємо за спаданням
            usort($asks, fn($a, $b) => (float)$a['price'] - (float)$b['price']); // Аски сортуємо за зростанням
            
            $orderBookLog = "\nOrder Book Details:\n";
            $orderBookLog .= "=== ASKS (Sell Orders) ===\n";
            foreach ($asks as $ask) {
                $orderBookLog .= sprintf(
                    "Price: %.12f | Amount: %.8f | ID: %d\n",
                    (float)$ask['price'],
                    (float)$ask['amount'],
                    $ask['id']
                );
            }
            
            $orderBookLog .= "\n=== BIDS (Buy Orders) ===\n";
            foreach ($bids as $bid) {
                $orderBookLog .= sprintf(
                    "Price: %.12f | Amount: %.8f | ID: %d\n",
                    (float)$bid['price'],
                    (float)$bid['amount'],
                    $bid['id']
                );
            }
            
            $this->logger->log("[{$this->pair}] {$orderBookLog}");
        } else {
            $this->logger->log("[{$this->pair}] No pending orders found");
        }

        return $pendingOrders;
    }

    /**
     * Clears all bot orders asynchronously.
     * Returns a promise that resolves when all cancellations are settled or immediately if no orders exist.
     */
    public function clearAllOrders(): PromiseInterface
    {
        $this->logger->log("!!!!! TradingBot::clearAllOrders(): [{$this->pair}] Starting async clearing of all orders...");
        
        try {
            // Отримання списку відкритих ордерів (залишаємо поки синхронним)
        $openOrders = $this->exchangeManager->getOpenOrders($this->pair);
            $orderCount = count($openOrders);
            $this->logger->log("!!!!! TradingBot::clearAllOrders(): [{$this->pair}] Found {$orderCount} open orders to clear.");

            if ($orderCount === 0) {
                $this->logger->log("!!!!! TradingBot::clearAllOrders(): [{$this->pair}] No orders to clear.");
                return \React\Promise\resolve(true);
            }
            
            // Створюємо масив промісів для скасування кожного ордера асинхронно
            $promises = array_map(function($order) {
                $orderId = $order['id'];
                // Викликаємо асинхронний метод менеджера, НЕ чекаємо тут
                return $this->exchangeManager->cancelOrder($orderId, $this->pair)
                    ->then(
                        function ($result) use ($orderId) {
                            // Успішна відповідь API (можливо, з помилкою всередині)
                            if (isset($result['error']) && $result['error'] !== null) {
                                if (isset($result['error']['code']) && $result['error']['code'] == 10) {
                                     $this->logger->log("[{$this->pair}] Order {$orderId} already done/cancelled (in promise).");
                                } else {
                                    $this->logger->error("[{$this->pair}] API error in cancel promise for order {$orderId}: " . json_encode($result['error']));
                                }
                            } else {
                                $this->logger->log("[{$this->pair}] Cancel API call for order {$orderId} succeeded (in promise).");
                            }
                            return $result; // Повертаємо результат для all()
                        },
                        function (\Throwable $e) use ($orderId) {
                            // Помилка HTTP або відхилення промісу з ExchangeManager
                            $this->logger->error("[{$this->pair}] Failed cancel promise for order {$orderId}: " . $e->getMessage());
                            // Можна повернути маркер помилки або повторно кинути виняток,
                            // щоб all() відхилився, якщо хоча б один запит невдалий.
                            // Повернемо null, щоб all() не впав одразу.
                            return null; 
                        }
                    );
            }, $openOrders);
            
            $this->logger->log("!!!!! TradingBot::clearAllOrders(): [{$this->pair}] Waiting for all {$orderCount} cancellation promises to settle...");

            // Чекаємо завершення ВСІХ асинхронних скасувань
            $results = await(all($promises)); 

            // Аналізуємо результати (опціонально)
            $successCount = 0;
            $errorCount = 0;
            foreach ($results as $index => $result) {
                if ($result === null) { // Наш маркер помилки з блоку rejection
                    $errorCount++;
                } elseif (isset($result['error']) && $result['error'] !== null) {
                    // Помилка API, яку ми не вважали за критичну раніше
                    if (!(isset($result['error']['code']) && $result['error']['code'] == 10)) {
                         $errorCount++; // Рахуємо як помилку, якщо це не 'not found'
                    }
                } else {
                    $successCount++;
                }
            }
            $this->logger->log("!!!!! TradingBot::clearAllOrders(): [{$this->pair}] All promises settled. Success API calls: {$successCount}, Failed/Error API calls: {$errorCount}");
            $this->logger->log("!!!!! TradingBot::clearAllOrders(): [{$this->pair}] Clearing process finished.");
            return \React\Promise\resolve($results);

        } catch (\Throwable $e) {
            // Catch synchronous errors (e.g., in getOpenOrders) or errors from await(all)
            $this->logger->error("!!!!! TradingBot::clearAllOrders(): [{$this->pair}] Exception during clearing process: " . $e->getMessage());
             $this->logger->logStackTrace("[{$this->pair}] Stack trace for clearAllOrders exception:");
             // Return a rejected promise
             return \React\Promise\reject($e); 
        }
    }

    /**
     * Places a limit order asynchronously.
     * 
     * @param int $side Side (1 for ask, 2 for bid)
     * @param string $amount Amount
     * @param string $price Price
     * @return PromiseInterface<int> Promise resolving with the Order ID or rejecting on failure.
     */
    public function placeLimitOrder(int $side, string $amount, string $price): PromiseInterface
    {
        $this->logger->log("[{$this->pair}] Queuing async limit order placement: side={$side}, price={$price}, amount={$amount}");
        
        // Delegate to ExchangeManager which returns a promise
        // TODO: Ensure ExchangeManager::placeLimitOrder exists and returns a Promise
        return $this->exchangeManager->placeLimitOrder($this->pair, $side, $amount, $price)
            ->then(
                function ($result) use ($side, $amount, $price) {
                    // Check for API errors in the resolved result
            if (isset($result['error']) && $result['error'] !== null) {
                        $errorJson = json_encode($result['error']);
                        $this->logger->error("[{$this->pair}] API error placing limit order (side={$side}, amount={$amount}, price={$price}): {$errorJson}");
                        // Reject the promise with an exception containing error details
                        throw new RuntimeException("Failed to place limit order due to API error: " . $errorJson);
                    }
                    
                    // Validate the structure and extract the ID
                    if (!isset($result['result']) || !isset($result['result']['id'])) {
                        $this->logger->error("[{$this->pair}] Invalid API response format (missing order ID): " . json_encode($result));
                        throw new RuntimeException("Invalid API response format when placing limit order: missing order ID");
                    }
                    
                    $orderId = (int)$result['result']['id'];
                    $this->logger->log("[{$this->pair}] Successfully placed limit order (side={$side}, price={$price}, amount={$amount}) - ID: {$orderId}");
                    return $orderId; // Resolve the promise with the integer Order ID
                },
                function (\Throwable $exception) use ($side, $amount, $price) {
                    // Handle transport errors or other exceptions from the HTTP request
                    $this->logger->error("[{$this->pair}] Exception placing limit order (side={$side}, amount={$amount}, price={$price}): " . $exception->getMessage());
                     $this->logger->logStackTrace("[{$this->pair}] Stack trace for placeLimitOrder exception:");
                    // Re-throw the exception to reject the promise
                    throw $exception;
                }
            );
    }

    /**
     * Places a market order asynchronously.
     * 
     * @param int $side Side (1 for sell, 2 for buy)
     * @param string $amount Amount
     * @return PromiseInterface<bool> Promise resolving with true on success (or simulated success), rejecting on failure.
     */
    public function placeMarketOrder(int $side, string $amount): PromiseInterface
    {
        $this->logger->log("[{$this->pair}] Queuing async market order placement: side={$side}, amount={$amount}");

        // TODO: Implement async market order placement in ExchangeManager
        // For now, simulate async success using resolve()
        return \React\Promise\resolve(true)->then(function() use ($side, $amount) {
            // Log simulated success within the promise chain
            $this->logger->log("[{$this->pair}] Simulated successful placement of market order (side={$side}, amount={$amount})");
            return true;
        }); 

        /* // Future implementation using ExchangeManager:
        // TODO: Ensure ExchangeManager::placeMarketOrder exists and returns a Promise
        return $this->exchangeManager->placeMarketOrder($this->pair, $side, $amount) 
            ->then(\n                function ($result) use ($side, $amount) {\n                    if (isset($result['error']) && $result['error'] !== null) {\n                        $errorJson = json_encode($result['error']);\n                        $this->logger->error(\"[{this->pair}] API error placing market order (side={$side}, amount={$amount}): {$errorJson}\");\n                        throw new RuntimeException(\"Failed to place market order due to API error: \" . $errorJson);\n                    }\n                    // Check result structure if needed\n                    $this->logger->log(\"[{this->pair}] Successfully placed market order (side={$side}, amount={$amount}): \" . json_encode($result));\n                    return true; // Or return specific data from result\n                },\n                function (\Throwable $exception) use ($side, $amount) {\n                    $this->logger->error(\"[{this->pair}] Exception placing market order (side={$side}, amount={$amount}): \" . $exception->getMessage());\n                    $this->logger->logStackTrace(\"[{this->pair}] Stack trace for placeMarketOrder exception:\");\n                    throw $exception;\n                }\n            );\n        */
    }

    /**
     * Scales order volumes to fit the bot's balance.
     *
     * @param array $orders Orders from external source
     * @param float $totalAvailable Available balance
     * @param bool $isLTC Whether it's base currency (true) or quote currency (false)
     * @return array Scaled orders
     */
    private function scaleVolumes(array $orders, float $totalAvailable, bool $isLTC): array
    {
        $totalVolume = array_reduce($orders, fn($sum, $order) => $sum + (float) $order[1], 0.0);
        $scaleFactor = $totalAvailable / $totalVolume;
        $minOrders = $this->pairConfig['settings']['min_orders'];

        return array_map(function ($order) use ($scaleFactor, $isLTC) {
            return [
                'price' => $order[0],
                'amount' => number_format((float) $order[1] * $scaleFactor, 8, '.', ''),
                'side' => $isLTC ? 2 : 1, // 2 - buy for base currency, 1 - sell for quote currency
            ];
        }, array_slice($orders, 0, $minOrders));
    }

    /**
     * Random delay in milliseconds using usleep.
     *
     * @param int $min Minimum delay in milliseconds
     * @param int $max Maximum delay in milliseconds
     */
    private function randomDelay(int $min, int $max): void
    {
        $delayMs = rand($min, $max); // Delay in milliseconds
        usleep($delayMs * 1000); // Convert to microseconds for accuracy
    }

    /**
     * Initializes the order book with initial orders.
     *
     * @param array $orderBook Order book from external source
     */
    private function initializeOrderBook(array $orderBook): void
    {
        $this->logger->log("[{$this->pair}] Initializing the order book");
        $tradeAmountMin = $this->pairConfig['settings']['trade_amount_min'];
        $tradeAmountMax = $this->pairConfig['settings']['trade_amount_max'];
        
        // Переконуємося, що min не більше max
        if ($tradeAmountMin > $tradeAmountMax) {
            $this->logger->error("[{$this->pair}] trade_amount_min > trade_amount_max, swapping values");
            $temp = $tradeAmountMin;
            $tradeAmountMin = $tradeAmountMax;
            $tradeAmountMax = $temp;
        }
        
        // Розрахунок ринкової ціни
        $marketPrice = $this->calculateMarketPrice($orderBook);
        
        // Отримання налаштувань для розподілу цін
        $priceFactor = $this->pairConfig['settings']['price_factor'];
        $marketGap = $this->pairConfig['settings']['market_gap'];
        $this->logger->log("[{$this->pair}] marketPrice: {$marketPrice}, using price_factor: {$priceFactor}%, market_gap: {$marketGap}%");
        
        // Отримання значень з settings
        $minOrders = $this->pairConfig['settings']['min_orders'];
        $maxOrders = $minOrders + 1; // Derive max_orders
        
        // Перетворення відсотків у десяткові дроби для розрахунків
        $priceFactorDecimal = $priceFactor / 100;
        $marketGapDecimal = $marketGap / 100;
        
        // Додаткове логування для параметрів ціноутворення
        $this->logger->log(
            sprintf(
                "[%s] Initializing order book with price distribution: price_factor=%.4f%%, market_gap=%.4f%%",
                $this->pair, 
                $priceFactor,
                $marketGap
            )
        );
        
        // Випадкова кількість ордерів в діапазоні
        $numOrders = mt_rand($minOrders, $maxOrders);
        
        $this->logger->log("[{$this->pair}] Initializing order book with {$numOrders} orders (min: {$minOrders}, max: {$maxOrders}), market price: {$marketPrice}");
        
        for ($i = 0; $i < $numOrders; $i++) {
            // Випадковий вибір сторони (bid або ask)
            $side = mt_rand(1, 2);
            
            // Розрахунок ціни для ордера - використовує price_factor та market_gap
            $price = $this->calculateOrderPrice($marketPrice, $side);
            
            // Генерація базового випадкового обсягу
            $randomFactor = mt_rand() / mt_getrandmax();
            $baseAmount = $tradeAmountMin + $randomFactor * ($tradeAmountMax - $tradeAmountMin);
            
            // Обчислення відхилення ціни від ринкової
            $priceDeviation = abs(($price - $marketPrice) / $marketPrice);
            
            // Корекція обсягу з урахуванням priceFactor і marketGap
            $amountAdjustment = 1 - ($priceDeviation * $priceFactorDecimal + $marketGapDecimal);
            if ($amountAdjustment < 0) {
                $amountAdjustment = 0; // Уникаємо від'ємних значень
            }
            $amount = $baseAmount * $amountAdjustment;
            $formattedAmount = number_format($amount, 8, '.', '');
            
            // Переконуємося, що обсяг не нульовий після форматування
            if ((float)$formattedAmount <= 0) {
                $this->logger->error("[{$this->pair}] Generated zero amount after formatting: original={$amount}, formatted={$formattedAmount}, using minimum");
                $formattedAmount = number_format($tradeAmountMin, 8, '.', '');
            }
            
            // Виводимо деталі про створений ордер разом з параметрами
            $priceDeviationPercent = $priceDeviation * 100;
            $sideText = ($side == 1 ? "ask" : "bid");
            $this->logger->log(
                sprintf(
                    "[%s] Initialized %s: %s @ %.12f (deviation: %.4f%% from market price, amount adjusted by: %.4f)",
                    $this->pair,
                    $sideText,
                    $formattedAmount,
                    $price,
                    $priceDeviationPercent,
                    $amountAdjustment
                )
            );
            
            // Розміщення ордера
            $this->placeLimitOrder($side, $formattedAmount, number_format($price, 12, '.', ''));
        }
        
        $this->logger->log("[{$this->pair}] Order book initialization complete with price_factor={$priceFactor}% and market_gap={$marketGap}%");
    }

    /**
     * Maintains the number of orders within min-max range for bids and asks.
     *
     * @param array $currentBids Current bids
     * @param array $currentAsks Current asks
     * @param float $marketPrice Market price
     * @param array $pendingOrders Open orders
     */
    private function maintainOrderCount(
        array &$currentBids,
        array &$currentAsks,
        float $marketPrice,
        array $pendingOrders,
    ): void {
        $minOrders = $this->pairConfig['settings']['min_orders'];
        $maxOrders = $minOrders + 1; // Derive max_orders
        $deviationPercent = $this->pairConfig['settings']['price_factor'] / 100;
        $marketGap = $this->pairConfig['settings']['market_gap'] / 100;
        
        $this->logger->log(
            sprintf(
                '[%s] Maintaining orders with min=%d, max=%d, deviation=%.2f%%, market_gap=%.2f%%',
                $this->pair,
                $minOrders,
                $maxOrders,
                $deviationPercent * 100,
                $marketGap * 100
            )
        );

        // Add bids if there are too few
        while (count($currentBids) < $minOrders) {
            // Використовуємо той самий алгоритм, що і в calculateOrderPrice
            $randBase = 0.05 + (mt_rand(0, 900) / 1000);
            $randomFactor = pow($randBase, 1/3);
            
            // Застосовуємо market_gap до ціни
            $gapAdjustment = $marketPrice * $marketGap;
            
            $bidPrice = number_format(
                $marketPrice * (1 - $deviationPercent * $randomFactor) - $gapAdjustment,
                12,
                '.',
                '',
            );
            $bidAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.19, 8, '.', '');
            $orderId = $this->placeLimitOrder(2, $bidAmount, $bidPrice);
            $this->logger->log(
                sprintf(
                    '[%s] Added bid to achieve %d-%d: %s @ %s (factor: %.4f, gap: %.6f)',
                    $this->pair,
                    $minOrders,
                    $maxOrders,
                    $bidAmount,
                    $bidPrice,
                    $randomFactor,
                    $gapAdjustment
                ),
            );
            $this->randomDelay(Config::DELAY_MAINTAIN_MIN, Config::DELAY_MAINTAIN_MAX);
            $currentBids[] = [
                'price' => $bidPrice,
                'amount' => $bidAmount,
                'side' => 2,
                'type' => 2,
                'id' => $orderId,
            ];
        }

        // Add asks if there are too few
        while (count($currentAsks) < $minOrders) {
            // Використовуємо той самий алгоритм, що і в calculateOrderPrice
            $randBase = 0.05 + (mt_rand(0, 900) / 1000);
            $randomFactor = pow($randBase, 1/3);
            
            // Застосовуємо market_gap до ціни
            $gapAdjustment = $marketPrice * $marketGap;
            
            $askPrice = number_format(
                $marketPrice * (1 + $deviationPercent * $randomFactor) + $gapAdjustment,
                12,
                '.',
                '',
            );
            $askAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.19, 8, '.', '');
            $orderId = $this->placeLimitOrder(1, $askAmount, $askPrice);
            $this->logger->log(
                sprintf(
                    '[%s] Added ask to achieve %d-%d: %s @ %s (factor: %.4f, gap: %.6f)',
                    $this->pair,
                    $minOrders,
                    $maxOrders,
                    $askAmount,
                    $askPrice,
                    $randomFactor,
                    $gapAdjustment
                ),
            );
            $this->randomDelay(Config::DELAY_MAINTAIN_MIN, Config::DELAY_MAINTAIN_MAX);
            $currentAsks[] = [
                'price' => $askPrice,
                'amount' => $askAmount,
                'side' => 1,
                'type' => 1,
                'id' => $orderId,
            ];
        }

        // Cancel excess bids (more than max)
        if (count($currentBids) > $maxOrders) {
            $bidsToCancelCount = count($currentBids) - $maxOrders;
            $this->logger->log("[{$this->pair}] Need to cancel {$bidsToCancelCount} excess bid(s).");
            $bids = array_values($currentBids);
            usort($bids, fn($a, $b) => (float) $a['price'] - (float) $b['price']); // Sort by lowest prices
            
            // Збираємо ідентифікатори для скасування
            $bidIdsToCancel = [];
            for ($i = 0; $i < $bidsToCancelCount && $i < count($bids); $i++) {
                $bidIdsToCancel[] = $bids[$i]['id'];
            }
            
            if (!empty($bidIdsToCancel)) {
                $this->logger->log("[{$this->pair}] Starting async cancellation of excess bids: " . implode(', ', $bidIdsToCancel));
                try {
                    // Створюємо масив промісів для скасування
                    $promises = array_map(function($orderId) {
                        return $this->exchangeManager->cancelOrder((int)$orderId, $this->pair)
                            ->then(null, function (\Throwable $e) use ($orderId) {
                                $this->logger->error("[{$this->pair}] Failed promise for cancelling excess bid {$orderId}: " . $e->getMessage());
                                return null; // Повертаємо null при помилці
                            });
                    }, $bidIdsToCancel);

                    // Чекаємо завершення всіх скасувань
                    await(all($promises)); 
                    $this->logger->log("[{$this->pair}] Finished async cancellation of excess bids.");

                    // Оновлюємо масив currentBids ПІСЛЯ завершення всіх скасувань
            $currentBids = array_filter($currentBids, function($bid) use ($bidIdsToCancel) {
                return !in_array($bid['id'], $bidIdsToCancel);
            });
                    $this->logger->log("[{$this->pair}] Updated currentBids count: " . count($currentBids));

                } catch (\Throwable $e) {
                    $this->logger->error("[{$this->pair}] Exception during await(all) for excess bid cancellation: " . $e->getMessage());
                    $this->logger->logStackTrace("[{$this->pair}] Stack trace for excess bid cancellation await(all):");
                    // Не оновлюємо $currentBids у разі помилки очікування
                }
            }
        }

        // Cancel excess asks (more than max)
        if (count($currentAsks) > $maxOrders) {
            $asksToCancelCount = count($currentAsks) - $maxOrders;
             $this->logger->log("[{$this->pair}] Need to cancel {$asksToCancelCount} excess ask(s).");
            $asks = array_values($currentAsks);
            usort($asks, fn($a, $b) => (float) $b['price'] - (float) $a['price']); // Sort by highest prices
            
            // Зберігаємо ідентифікатори для скасування
            $askIdsToCancel = [];
            for ($i = 0; $i < $asksToCancelCount && $i < count($asks); $i++) {
                $askIdsToCancel[] = $asks[$i]['id'];
            }
            
            if (!empty($askIdsToCancel)) {
                 $this->logger->log("[{$this->pair}] Starting async cancellation of excess asks: " . implode(', ', $askIdsToCancel));
                try {
                    // Створюємо масив промісів для скасування
                    $promises = array_map(function($orderId) {
                         return $this->exchangeManager->cancelOrder((int)$orderId, $this->pair)
                            ->then(null, function (\Throwable $e) use ($orderId) {
                                $this->logger->error("[{$this->pair}] Failed promise for cancelling excess ask {$orderId}: " . $e->getMessage());
                                return null; // Повертаємо null при помилці
                            });
                    }, $askIdsToCancel);

                    // Чекаємо завершення всіх скасувань
                    await(all($promises));
                    $this->logger->log("[{$this->pair}] Finished async cancellation of excess asks.");

                    // Оновлюємо масив currentAsks ПІСЛЯ завершення всіх скасувань
            $currentAsks = array_filter($currentAsks, function($ask) use ($askIdsToCancel) {
                return !in_array($ask['id'], $askIdsToCancel);
            });
                     $this->logger->log("[{$this->pair}] Updated currentAsks count: " . count($currentAsks));

                } catch (\Throwable $e) {
                    $this->logger->error("[{$this->pair}] Exception during await(all) for excess ask cancellation: " . $e->getMessage());
                     $this->logger->logStackTrace("[{$this->pair}] Stack trace for excess ask cancellation await(all):");
                    // Не оновлюємо $currentAsks у разі помилки очікування
                }
            }
        }
    }

    /**
     * Perform a random action based on market maker probability
     * 
     * @param array $currentBids Current bids
     * @param array $currentAsks Current asks
     * @param array $pendingOrders Open orders
     * @param float $marketPrice Market price
     */
    private function performRandomAction(
        array &$currentBids,
        array &$currentAsks,
        array $pendingOrders,
        float $marketPrice,
    ): void {
        // Делегуємо виконання дії на MarketMakerActions
        $this->marketMakerActions->performRandomAction(
            $currentBids,
            $currentAsks,
            $pendingOrders,
            $marketPrice
        );
    }

    /**
     * Calculates the market price as the average between the best bid and ask.
     *
     * @param array $orderBook Order book from external source
     * @return float Market price
     */
    private function calculateMarketPrice(array $orderBook): float
    {
        return (floatval($orderBook['bids'][0][0]) + floatval($orderBook['asks'][0][0])) / 2;
    }

    /**
     * Calculates the price for a new order based on the market price
     */
    private function calculateOrderPrice(float $marketPrice, int $side): float
    {
        // Отримання налаштувань з settings
        $deviationPercent = $this->pairConfig['settings']['price_factor'];
        $marketGap = $this->pairConfig['settings']['market_gap'];
        

        // Логування для діагностики
        $this->logger->log(sprintf('[%s] Price calculation using deviation=%.4f%%, market_gap=%.4f%%', 
        $this->pair, $deviationPercent, $marketGap));
            
        // Перетворення відсотків у десяткові дроби
        $deviationFactor = $deviationPercent / 100;
        $marketGapFactor = $marketGap / 100;
        
        // Генеруємо випадкове число від 0 до 1 для розподілу ордерів
        $randBase = mt_rand(0, 1000) / 1000;
        
        // Використовуємо квадратичну функцію для кращого розподілу
        // Це дасть нам більше ордерів ближче до ринкової ціни
        $randomFactor = $randBase * $randBase;
        
        // Спочатку застосовуємо базовий market gap
        $basePrice = $marketPrice;
        if ($side === 1) { // Ask (sell)
            $basePrice += $marketPrice * $marketGapFactor;
        } else { // Bid (buy)
            $basePrice -= $marketPrice * $marketGapFactor;
        }
        
        // Потім застосовуємо price factor до ціни з gap
        if ($side === 1) { // Ask (sell)
            $finalPrice = $basePrice * (1 + ($deviationFactor * $randomFactor));
        } else { // Bid (buy)
            $finalPrice = $basePrice * (1 - ($deviationFactor * $randomFactor));
        }
        
        // Детальне логування
        $this->logger->log(sprintf(
            '[%s] Price calculation details: marketPrice=%.6f, basePrice=%.6f, side=%d, randomFactor=%.4f, deviation=%.2f%%, finalPrice=%.6f',
            $this->pair,
            $marketPrice,
            $basePrice,
            $side,
            $randomFactor,
            ($finalPrice - $marketPrice) / $marketPrice * 100,
            $finalPrice
        ));
        
        return $finalPrice;
    }

    /**
     * Maintains orders based on the order book and current orders
     */
    private function maintainOrders(array $orderBook, array $pendingOrders): void
    {
        // Calculating the market price
        $marketPrice = $this->calculateMarketPrice($orderBook);
        
        $marketGap = $this->pairConfig['settings']['market_gap'];
        $gapAdjustment = $marketPrice * ($marketGap / 100);
        
        // Applying the market_gap to the best prices
        $bestBidPrice = $orderBook['bids'][0][0] - $gapAdjustment;
        $bestAskPrice = $orderBook['asks'][0][0] + $gapAdjustment;
        
        // Getting the current bids and asks from pending orders
        $currentBids = array_filter($pendingOrders, fn($o) => $o['side'] === 2);
        $currentAsks = array_filter($pendingOrders, fn($o) => $o['side'] === 1);
        
        $this->logger->log(
            sprintf(
                '[%s] Market price: %.12f, best bid = %.12f, best ask = %.12f',
                $this->pair,
                $marketPrice,
                $bestBidPrice,
                $bestAskPrice,
            ),
        );
        
        $this->logger->log(
            sprintf(
                '[%s] Current bids: %d, Current asks: %d, Open orders: %d',
                $this->pair,
                count($currentBids),
                count($currentAsks),
                count($pendingOrders),
            ),
        );
        
        // Maintaining the order count
        $this->maintainOrderCount($currentBids, $currentAsks, $marketPrice, $pendingOrders);
        
        // Performing a random action
        $this->performRandomAction($currentBids, $currentAsks, $pendingOrders, $marketPrice);
    }

    // Оновлення списку відкритих ордерів перед кожною операцією
    public function updateOpenOrders() {
        try {
            $this->openOrders = $this->exchangeManager->getOpenOrders($this->pair);
            $this->logger->log("[{$this->pair}] Updated open orders list, found " . count($this->openOrders) . " orders");
        } catch (Exception $e) {
            $this->logger->error("[{$this->pair}] Error updating open orders: " . $e->getMessage());
            // Додаємо логування стек трейсу для відстеження джерела помилки
            $this->logger->logStackTrace("[{$this->pair}] Stack trace for updating open orders error:");
        }
    }

    /**
     * Get the current list of open orders
     * 
     * @return array Open orders
     */
    public function getOpenOrders(): array
    {
        return $this->openOrders;
    }

    /**
     * Returns the ExchangeManager instance.
     */
    public function getExchangeManager(): ExchangeManager
    {
        return $this->exchangeManager;
    }
}
