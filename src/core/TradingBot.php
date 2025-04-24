<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';  
require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/ExchangeManager.php';
require_once __DIR__ . '/MarketMakerActions.php';

/**
 * TradingBot - an automated bot for simulating trades on a cryptocurrency exchange.
 */
class TradingBot
{
    private string $pair;
    private array $pairConfig;
    private Logger $logger;
    private ExchangeManager $exchangeManager;
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
        
        // Get configuration for the pair
        $this->pairConfig = $dynamicConfig ?? Config::getPairConfig($pair);
        
        $this->apiClient = new ApiClient();
        $this->logger = Logger::getInstance();
        $this->exchangeManager = ExchangeManager::getInstance();
        
        $this->logger->log("Created a bot for the pair {$pair}");
        
        // Logging the loaded configuration for diagnostics
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

        // Initialize the MarketMakerActions class
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
        $this->logger->log("!!!!! TradingBot: Configuration update for pair {$this->pair}");
        
        // Save old values for logging
        $oldFrequencyFrom = $this->pairConfig['settings']['frequency_from'] ?? 'Not set';
        $oldFrequencyTo = $this->pairConfig['settings']['frequency_to'] ?? 'Not set';
        $oldOrderCount = $this->pairConfig['settings']['order_count'] ?? 'Not set';
        $oldVolume = $this->pairConfig['settings']['volume'] ?? 'Not set';
        
        // Log changes in main parameters
        $newFrequencyFrom = $this->pairConfig['settings']['frequency_from'] ?? 'Not set';
        $newFrequencyTo = $this->pairConfig['settings']['frequency_to'] ?? 'Not set';
        $newOrderCount = $this->pairConfig['settings']['order_count'] ?? 'Not set';
        $newVolume = $this->pairConfig['settings']['volume'] ?? 'Not set';
        
        $this->logger->log("!!!!! TradingBot: Changes in configuration for pair {$this->pair}:");
        $this->logger->log("!!!!! TradingBot: - Frequency from: {$oldFrequencyFrom} -> {$newFrequencyFrom}");
        $this->logger->log("!!!!! TradingBot: - Frequency to: {$oldFrequencyTo} -> {$newFrequencyTo}");
        $this->logger->log("!!!!! TradingBot: - Number of orders: {$oldOrderCount} -> {$newOrderCount}");
        $this->logger->log("!!!!! TradingBot: - Volume: {$oldVolume} -> {$newVolume}");
        
        // Update strategy if it has changed
        if (isset($newConfig['settings']['strategy']) && isset($this->pairConfig['settings']['strategy'])) {
            $oldStrategy = $this->marketMakerActions ? get_class($this->marketMakerActions) : 'Not set';
            $this->logger->log("!!!!! TradingBot: - Strategy: {$oldStrategy} -> will be updated during initialization");
        }
        
        $this->logger->log("!!!!! TradingBot: Configuration for pair {$this->pair} successfully updated");
    }

    /**
     * Initializes the bot by clearing orders and setting up initial order book
     */
    public function initialize(): void
    {
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Starting bot initialization...");
        
        if ($this->isInitialized) {
            $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Bot is already initialized, skipping initialization");
            return;
        }
        
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Bot is not initialized, performing initialization...");
        
        // Clearing all orders
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Clearing all orders");
        $this->clearAllOrders();
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] All orders cleared");
        
        // Getting order book from external API
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Requesting order book from external API");
        $orderBook = $this->getExternalOrderBook();
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Received order book: " . count($orderBook['bids']) . " bid, " . count($orderBook['asks']) . " ask");
        
        // Initializing initial orders
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Initializing order book");
        $this->initializeOrderBook($orderBook);
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Order book initialized");
        
        $this->isInitialized = true;
        $this->logger->log("!!!!! TradingBot::initialize(): [{$this->pair}] Bot initialization successfully completed");
    }

    /**
     * Runs a single trading cycle.
     */
    public function runSingleCycle(): void
    {
        if (!$this->isInitialized) {
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Bot is not initialized, skipping cycle");
            return;
        }
        
        $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Starting trading cycle");
        
        try {
            // Get order book
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Requesting order book");
            $orderBook = $this->getExternalOrderBook();
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Received order book: " . count($orderBook['bids']) . " bid, " . count($orderBook['asks']) . " ask");
            
            // Get current orders
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Requesting current orders");
            $pendingOrders = $this->getPendingOrders();
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Received current orders: " . count($pendingOrders));
            
            // Maintain orders
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Starting order maintenance");
            $this->maintainOrders($orderBook, $pendingOrders);
            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Order maintenance completed");

            $this->logger->log("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Trading cycle successfully completed");
        } catch (Exception $e) {
            $this->logger->error("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Error in trading cycle: " . $e->getMessage());
            // Add stack trace logging to track the source of the error
            $this->logger->logStackTrace("!!!!! TradingBot::runSingleCycle(): [{$this->pair}] Trading cycle error stack trace:");
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
                // Add stack trace logging to track the source of the error
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
            // Add stack trace logging to track the source of the error
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
        
        // Detailed logging of the order book
        if (!empty($pendingOrders)) {
            $bids = array_filter($pendingOrders, fn($o) => $o['side'] === 2);
            $asks = array_filter($pendingOrders, fn($o) => $o['side'] === 1);
            
            // Sort orders by price
            usort($bids, fn($a, $b) => (float)$b['price'] - (float)$a['price']); // Sort bids in descending order
            usort($asks, fn($a, $b) => (float)$a['price'] - (float)$b['price']); // Sort asks in ascending order
            
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
     * Clears all bot orders.
     */
    public function clearAllOrders(): void
    {
        $this->logger->log("!!!!! TradingBot::clearAllOrders(): [{$this->pair}] Starting clearing all orders");
        
        // Getting list of open orders
        $openOrders = $this->exchangeManager->getOpenOrders($this->pair);
        $this->logger->log("!!!!! TradingBot::clearAllOrders(): [{$this->pair}] Found " . count($openOrders) . " open orders for clearing");
        
        // Collecting all IDs for cancellation
        $orderIds = array_map(function($order) {
            return $order['id'];
        }, $openOrders);
        
        // Cancelling orders in batch
        foreach ($orderIds as $orderId) {
            $this->logger->log("!!!!! TradingBot::clearAllOrders(): [{$this->pair}] Cancelling order: {$orderId}");
            $this->cancelOrder($orderId);
            // Add a small delay between order cancellations
            usleep(100000); // 100 ms
        }
        
        $this->logger->log("!!!!! TradingBot::clearAllOrders(): [{$this->pair}] All orders cleared");
    }

    /**
     * Places a limit order.
     * 
     * @param int $side Side (1 for ask, 2 for bid)
     * @param string $amount Amount
     * @param string $price Price
     * @return int Order ID
     */
    public function placeLimitOrder(int $side, string $amount, string $price): int
    {
        $this->logger->log("[{$this->pair}] Placing a limit order price={$price}, amount={$amount}, for side={$side}, amount={$amount},  with fees: " . Config::TAKER_FEE . " and " . Config::MAKER_FEE);
        $body = [
            'method' => 'order.put_limit',
            'params' => [
                Config::BOT_USER_ID,
                $this->pair,
                $side,
                $amount,
                $price,
                Config::TAKER_FEE,
                Config::MAKER_FEE,
                Config::ORDER_SOURCE,
            ],
            'id' => 1,
        ];
        $response = $this->apiClient->post(Config::getTradeServerUrl(), json_encode($body));
        // $this->logger->log("[{$this->pair}] Placed order: " . json_encode($body, JSON_PRETTY_PRINT));
        $data = json_decode($response, true);

        if ($data['error'] !== null) {
            $this->logger->log(
                sprintf(
                    '[%s] Result of placing an order for side=%d: %s',
                    $this->pair,
                    $side,
                    json_encode($data, JSON_PRETTY_PRINT),
                ),
            );
            throw new RuntimeException("Failed to place limit order: " . json_encode($data['error']));
        }

        if (!isset($data['result']) || !isset($data['result']['id'])) {
            throw new RuntimeException("Invalid response format: missing order ID");
        }

        $orderId = (int)$data['result']['id'];

        // ADD - Send heartbeat to Dead Watcher after successful limit order placement
        if (Config::isDeadWatcherEnabled()) {
            $this->logger->log("!!!!! TradingBot::placeLimitOrder(): [{$this->pair}] Sending heartbeat to Dead Watcher after placing limit order {$orderId}");
            $this->sendHeartbeatToDeadWatcher();
        }

        return $orderId;
    }

    /**
     * Cancels an order.
     * 
     * @param int $orderId Order ID
     */
    public function cancelOrder(int $orderId): void
    {
        try {
            $result = $this->exchangeManager->cancelOrder($orderId, $this->pair);
            
            if (isset($result['error']) && $result['error'] !== null) {
                // Check for "order not found" error
                if (isset($result['error']['code']) && $result['error']['code'] == 10) {
                    $this->logger->log("[{$this->pair}] Order {$orderId} already executed or cancelled, skipping");
                } else {
                    $this->logger->error("[{$this->pair}] Error cancelling order {$orderId}: " . json_encode($result['error']));
                    // Add stack trace logging for error in API result
                    $this->logger->logStackTrace("[{$this->pair}] Stack trace for cancelling order error (API result):");
                }
            } else {
                $this->logger->log("[{$this->pair}] Successfully cancelled order {$orderId}");
            }
            
            $this->randomDelay(Config::DELAY_CLEAR_MIN, Config::DELAY_CLEAR_MAX);
        } catch (Exception $e) {
            $this->logger->error("[{$this->pair}] Exception when cancelling order {$orderId}: " . $e->getMessage());
            // Add stack trace logging to track the source of the error
            $this->logger->logStackTrace("[{$this->pair}] Stack trace for cancelling order exception:");
        }
    }

    /**
     * Places a market order.
     * 
     * @param int $side Side (1 for sell, 2 for buy)
     * @param string $amount Amount
     * @return bool Success
     */
    public function placeMarketOrder(int $side, string $amount): bool
    {
        $this->logger->log("[{$this->pair}] Placing a market order for side={$side}, amount={$amount}");
        
        $body = [
            'method' => 'order.put_market',
            'params' => [
                Config::BOT_USER_ID,
                $this->pair,
                $side,
                $amount,
                Config::TAKER_FEE,
                Config::MARKET_TRADE_SOURCE,
            ],
            'id' => 1,
        ];
        $response = $this->apiClient->post(Config::getTradeServerUrl(), json_encode($body));
        $data = json_decode($response, true);

        // Log the actual server response for debugging
        $this->logger->log(sprintf(
            '[%s] Server response for placeMarketOrder (side=%d): %s',
            $this->pair,
            $side,
            $response // Log the raw response string
            // json_encode($data, JSON_PRETTY_PRINT) // Or log the parsed data
        ));

        // ADD - Check the actual response from the server
        if ($data['error'] !== null) {
             $this->logger->error(
                sprintf(
                    '[%s] Failed to place market order for side=%d: %s',
                    $this->pair,
                    $side,
                    json_encode($data['error'], JSON_PRETTY_PRINT),
                ),
            );
            // Decide how to handle the error. Returning false might be appropriate.
            // Or throw an exception if it's critical.
            return false; 
            // throw new RuntimeException("Failed to place market order: " . json_encode($data['error']));
        }

        // ADD - Send heartbeat to Dead Watcher after successful market order placement
        if (Config::isDeadWatcherEnabled()) {
            $this->logger->log("!!!!! TradingBot::placeMarketOrder(): [{$this->pair}] Sending heartbeat to Dead Watcher after placing market order");
            $this->sendHeartbeatToDeadWatcher();
        }
        
        return true; // Return true only on success
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
            // $bidAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.19, 8, '.', ''); // REMOVE THIS LINE
            
            // Read min/max trade amounts from config
            $tradeAmountMin = (float) $this->pairConfig['settings']['trade_amount_min'];
            $tradeAmountMax = (float) $this->pairConfig['settings']['trade_amount_max'];
            
            // Ensure min is not greater than max
            if ($tradeAmountMin > $tradeAmountMax) {
                $this->logger->warning("[{$this->pair}] trade_amount_min > trade_amount_max in maintainOrderCount (bid), swapping.");
                [$tradeAmountMin, $tradeAmountMax] = [$tradeAmountMax, $tradeAmountMin];
            }
            
            // Calculate random amount within the configured range
            $randomAmountFactor = mt_rand() / mt_getrandmax(); // 0.0 to 1.0
            $amount = $tradeAmountMin + $randomAmountFactor * ($tradeAmountMax - $tradeAmountMin);
            $bidAmount = number_format($amount, 8, '.', '');
            
            // Ensure amount is not zero after formatting
            if ((float)$bidAmount <= 0) {
                $this->logger->error("[{$this->pair}] Generated zero bid amount in maintainOrderCount: original={$amount}, using minimum: {$tradeAmountMin}");
                $bidAmount = number_format($tradeAmountMin, 8, '.', '');
            }
            
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
            // $askAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.19, 8, '.', ''); // REMOVE THIS LINE
            
            // Read min/max trade amounts from config
            $tradeAmountMin = (float) $this->pairConfig['settings']['trade_amount_min'];
            $tradeAmountMax = (float) $this->pairConfig['settings']['trade_amount_max'];
            
            // Ensure min is not greater than max
            if ($tradeAmountMin > $tradeAmountMax) {
                 $this->logger->warning("[{$this->pair}] trade_amount_min > trade_amount_max in maintainOrderCount (ask), swapping.");
                 [$tradeAmountMin, $tradeAmountMax] = [$tradeAmountMax, $tradeAmountMin];
            }
            
            // Calculate random amount within the configured range
            $randomAmountFactor = mt_rand() / mt_getrandmax(); // 0.0 to 1.0
            $amount = $tradeAmountMin + $randomAmountFactor * ($tradeAmountMax - $tradeAmountMin);
            $askAmount = number_format($amount, 8, '.', '');
            
            // Ensure amount is not zero after formatting
            if ((float)$askAmount <= 0) {
                 $this->logger->error("[{$this->pair}] Generated zero ask amount in maintainOrderCount: original={$amount}, using minimum: {$tradeAmountMin}");
                 $askAmount = number_format($tradeAmountMin, 8, '.', '');
            }
            
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
            $bidsToCancel = count($currentBids) - $maxOrders;
            $bids = array_values($currentBids);
            usort($bids, fn($a, $b) => (float) $a['price'] - (float) $b['price']); // Sort by lowest prices
            
            // Зберігаємо ідентифікатори для скасування
            $bidIdsToCancel = [];
            for ($i = 0; $i < $bidsToCancel && $i < count($bids); $i++) {
                $bidIdsToCancel[] = $bids[$i]['id'];
            }
            
            // Скасовуємо ордери за списком
            foreach ($bidIdsToCancel as $orderId) {
                $this->cancelOrder($orderId);
                $this->logger->log(
                    sprintf('[%s] Cancelled excess bid: %d', $this->pair, $orderId)
                );
                $this->randomDelay(Config::DELAY_MAINTAIN_MIN, Config::DELAY_MAINTAIN_MAX);
            }
            
            // Оновлюємо масив currentBids після скасування
            $currentBids = array_filter($currentBids, function($bid) use ($bidIdsToCancel) {
                return !in_array($bid['id'], $bidIdsToCancel);
            });
        }

        // Cancel excess asks (more than max)
        if (count($currentAsks) > $maxOrders) {
            $asksToCancel = count($currentAsks) - $maxOrders;
            $asks = array_values($currentAsks);
            usort($asks, fn($a, $b) => (float) $b['price'] - (float) $a['price']); // Sort by highest prices
            
            // Зберігаємо ідентифікатори для скасування
            $askIdsToCancel = [];
            for ($i = 0; $i < $asksToCancel && $i < count($asks); $i++) {
                $askIdsToCancel[] = $asks[$i]['id'];
            }
            
            // Скасовуємо ордери за списком
            foreach ($askIdsToCancel as $orderId) {
                $this->cancelOrder($orderId);
                $this->logger->log(
                    sprintf('[%s] Cancelled excess ask: %d', $this->pair, $orderId)
                );
                $this->randomDelay(Config::DELAY_MAINTAIN_MIN, Config::DELAY_MAINTAIN_MAX);
            }
            
            // Оновлюємо масив currentAsks після скасування
            $currentAsks = array_filter($currentAsks, function($ask) use ($askIdsToCancel) {
                return !in_array($ask['id'], $askIdsToCancel);
            });
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
}
