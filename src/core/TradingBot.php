<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';  
require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/ExchangeManager.php';

/**
 * TradingBot - an automated bot for simulating trades on a cryptocurrency exchange.
 */
class TradingBot
{
    private bool $lastActionWasSell = false;
    private ApiClient $apiClient;
    private Logger $logger;
    private string $pair;
    private array $pairConfig;
    private bool $initialized = false;
    private ?array $dynamicConfig;
    private ExchangeManager $exchangeManager;
    private array $openOrders = [];

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
            'Settings for %s: min_orders=%s, max_orders=%s, trade_amount_min=%s, trade_amount_max=%s, frequency_from=%s, frequency_to=%s, probability=%s',
            $pair,
            $settings['min_orders'],
            $settings['max_orders'],
            $settings['trade_amount_min'],
            $settings['trade_amount_max'],
            $settings['frequency_from'],
            $settings['frequency_to'],
            $settings['market_maker_order_probability']
        ));
    }

    /**
     * Initializes the bot by clearing orders and setting up initial order book
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->logger->log("Initialization of the bot for the pair {$this->pair}...");
        $this->clearAllOrders();
        
        // Getting the order book from the external API
        $orderBook = $this->getExternalOrderBook();
        
        // Initializing the initial orders
        $this->initializeOrderBook($orderBook);
        
        $this->initialized = true;
        $this->logger->log("The bot for the pair {$this->pair} has been initialized");
    }

    /**
     * Runs a single trading cycle.
     */
    public function runSingleCycle(): void
    {
        if (!$this->initialized) {
            $this->logger->log("[{$this->pair}] The bot is not initialized, skipping the cycle");
            return;
        }
        
        $this->logger->log("[{$this->pair}] Starting the trading cycle");
        
        try {
            // Getting the order book
            $orderBook = $this->getExternalOrderBook();
            $this->logger->log("[{$this->pair}] Received the order book: " . count($orderBook['bids']) . " bids, " . count($orderBook['asks']) . " asks");
            
            // Getting the current orders
            $pendingOrders = $this->getPendingOrders();
            $this->logger->log("[{$this->pair}] Received the current orders: " . count($pendingOrders));
            
            // Maintaining the orders
            $this->maintainOrders($orderBook, $pendingOrders);
            
            $this->logger->log("[{$this->pair}] The trading cycle has been completed successfully");
        } catch (Exception $e) {
            $this->logger->error("[{$this->pair}] Error in the trading cycle: " . $e->getMessage());
            // Додаємо логування стек трейсу для відстеження джерела помилки
            $this->logger->logStackTrace("[{$this->pair}] Stack trace for trading cycle error:");
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

        return $data['result']['records'] ?? [];
    }

    /**
     * Clears all bot orders.
     */
    public function clearAllOrders(): void
    {
        $this->logger->log("[{$this->pair}] Clearing all orders");
        
        // Отримання списку відкритих ордерів
        $openOrders = $this->exchangeManager->getOpenOrders($this->pair);
        $this->logger->log("[{$this->pair}] Found " . count($openOrders) . " open orders to clear");
        
        // Збираємо всі ID для скасування
        $orderIds = array_map(function($order) {
            return $order['id'];
        }, $openOrders);
        
        // Скасовуємо ордери пакетно
        foreach ($orderIds as $orderId) {
            $this->cancelOrder($orderId);
            // Додаємо невелику затримку між скасуваннями ордерів
            usleep(100000); // 100 мс
        }
        
        $this->logger->log("[{$this->pair}] All orders cleared");
    }

    /**
     * Places a limit order.
     *
     * @param int $side Order type: 1 - sell, 2 - buy
     * @param string $amount Order volume
     * @param string $price Order price
     * @return mixed
     * @throws RuntimeException If the request fails
     */
    private function placeLimitOrder(int $side, string $amount, string $price)
    {
        $this->logger->log("[{$this->pair}] Placing a limit order for side={$side}, amount={$amount}, price={$price} with fees: " . Config::TAKER_FEE . " and " . Config::MAKER_FEE);
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
        }

        return $data;
    }

    /**
     * Cancels a specific order by ID.
     *
     * @param int $orderId Order ID to cancel
     */
    private function cancelOrder(int $orderId): void
    {
        try {
            $this->logger->log("[{$this->pair}] Cancelling order {$orderId}");
            $result = $this->exchangeManager->cancelOrder($orderId, $this->pair);
            
            if (isset($result['error']) && $result['error'] !== null) {
                // Перевіряємо на помилку "order not found"
                if (isset($result['error']['code']) && $result['error']['code'] == 10) {
                    $this->logger->log("[{$this->pair}] Order {$orderId} already executed or cancelled, skipping");
                } else {
                    $this->logger->error("[{$this->pair}] Error cancelling order {$orderId}: " . json_encode($result['error']));
                    // Додаємо логування стек трейсу при помилці в результаті запиту
                    $this->logger->logStackTrace("[{$this->pair}] Stack trace for cancelling order error (API result):");
                }
            } else {
                $this->logger->log("[{$this->pair}] Successfully cancelled order {$orderId}");
            }
            
            $this->randomDelay(Config::DELAY_CLEAR_MIN, Config::DELAY_CLEAR_MAX);
        } catch (Exception $e) {
            $this->logger->error("[{$this->pair}] Exception when cancelling order {$orderId}: " . $e->getMessage());
            // Додаємо логування стек трейсу для відстеження джерела помилки
            $this->logger->logStackTrace("[{$this->pair}] Stack trace for cancelling order exception:");
        }
    }

    /**
     * Executes a market order (simulating trades).
     *
     * @param int $side Order type: 1 - sell, 2 - buy
     * @param string $amount Order volume
     * @return bool
     */
    private function placeMarketOrder(int $side, string $amount): bool
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

        return true; // Simulation of successful execution
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
        
        // Отримання значень з settings
        $minOrders = $this->pairConfig['settings']['min_orders'];
        $maxOrders = $this->pairConfig['settings']['max_orders'];
        
        // Випадкова кількість ордерів в діапазоні
        $numOrders = mt_rand($minOrders, $maxOrders);
        
        $this->logger->log("[{$this->pair}] Initializing order book with {$numOrders} orders (min: {$minOrders}, max: {$maxOrders}), market price: {$marketPrice}");
        
        for ($i = 0; $i < $numOrders; $i++) {
            // Випадковий вибір сторони (bid або ask)
            $side = mt_rand(1, 2);
            
            // Генерація випадкового обсягу в діапазоні
            $randomFactor = mt_rand() / mt_getrandmax();
            $amount = $tradeAmountMin + $randomFactor * ($tradeAmountMax - $tradeAmountMin);
            $formattedAmount = number_format($amount, 8, '.', '');
            
            // Переконуємося, що обсяг не нульовий після форматування
            if ((float)$formattedAmount <= 0) {
                $this->logger->error("[{$this->pair}] Generated zero amount after formatting: original={$amount}, formatted={$formattedAmount}, using minimum");
                $formattedAmount = number_format($tradeAmountMin, 8, '.', '');
            }
            
            // Розрахунок ціни для ордера
            $price = $this->calculateOrderPrice($marketPrice, $side);
            
            $this->logger->log("[{$this->pair}] Initialized " . ($side == 1 ? "ask" : "bid") . ": {$formattedAmount} @ " . number_format($price, 12, '.', ''));
            
            // Розміщення ордера
            $this->placeLimitOrder($side, $formattedAmount, number_format($price, 12, '.', ''));
        }
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
        $maxOrders = $this->pairConfig['settings']['max_orders'];
        $deviationPercent = $this->pairConfig['settings']['price_factor'] / 100;
        
        $this->logger->log(
            sprintf(
                '[%s] Maintaining orders with min=%d, max=%d, deviation=%.2f%%',
                $this->pair,
                $minOrders,
                $maxOrders,
                $deviationPercent * 100
            )
        );

        // Add bids if there are too few
        while (count($currentBids) < $minOrders) {
            // Використовуємо той самий алгоритм, що і в calculateOrderPrice
            $randBase = 0.05 + (mt_rand(0, 900) / 1000);
            $randomFactor = pow($randBase, 1/3);
            
            $bidPrice = number_format(
                $marketPrice * (1 - $deviationPercent + $randomFactor * $deviationPercent),
                12,
                '.',
                '',
            );
            $bidAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.19, 8, '.', '');
            $this->placeLimitOrder(2, $bidAmount, $bidPrice);
            $this->logger->log(
                sprintf(
                    '[%s] Added bid to achieve %d-%d: %s @ %s (factor: %.4f)',
                    $this->pair,
                    $minOrders,
                    $maxOrders,
                    $bidAmount,
                    $bidPrice,
                    $randomFactor
                ),
            );
            $this->randomDelay(Config::DELAY_MAINTAIN_MIN, Config::DELAY_MAINTAIN_MAX);
            $currentBids[] = [
                'price' => $bidPrice,
                'amount' => $bidAmount,
                'side' => 2,
                'type' => 2,
                'id' => time(),
            ];
        }

        // Add asks if there are too few
        while (count($currentAsks) < $minOrders) {
            // Використовуємо той самий алгоритм, що і в calculateOrderPrice
            $randBase = 0.05 + (mt_rand(0, 900) / 1000);
            $randomFactor = pow($randBase, 1/3);
            
            $askPrice = number_format(
                $marketPrice * (1 + $deviationPercent + $randomFactor * $deviationPercent),
                12,
                '.',
                '',
            );
            $askAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.19, 8, '.', '');
            $this->placeLimitOrder(1, $askAmount, $askPrice);
            $this->logger->log(
                sprintf(
                    '[%s] Added ask to achieve %d-%d: %s @ %s (factor: %.4f)',
                    $this->pair,
                    $minOrders,
                    $maxOrders,
                    $askAmount,
                    $askPrice,
                    $randomFactor
                ),
            );
            $this->randomDelay(Config::DELAY_MAINTAIN_MIN, Config::DELAY_MAINTAIN_MAX);
            $currentAsks[] = [
                'price' => $askPrice,
                'amount' => $askAmount,
                'side' => 1,
                'type' => 1,
                'id' => time(),
            ];
        }

        // Cancel excess bids (more than max)
        if (count($currentBids) > $maxOrders) {
            $bidsToCancel = count($currentBids) - $maxOrders;
            $this->updateOpenOrders(); // Оновлюємо список тільки один раз перед пачкою скасувань
            $bids = array_filter($this->openOrders, fn($o) => $o['side'] === 2);
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
        }

        // Cancel excess asks (more than max)
        if (count($currentAsks) > $maxOrders) {
            $asksToCancel = count($currentAsks) - $maxOrders;
            $this->updateOpenOrders(); // Оновлюємо список тільки один раз перед пачкою скасувань
            $asks = array_filter($this->openOrders, fn($o) => $o['side'] === 1);
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
        }
    }

    /**
     * Performs random actions (adding, canceling, simulating trades).
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
        $maxOrders = $this->pairConfig['settings']['max_orders'];
        $deviationPercent = $this->pairConfig['settings']['price_factor'] / 100;
        
        // Ймовірність створення ордерів маркет-мейкера
        $marketMakerProbability = $this->pairConfig['settings']['market_maker_order_probability'] / 100;
        
        $this->logger->log(sprintf(
            '[%s] Performing random actions with max_orders=%d, deviation=%.4f%%, probability=%.2f', 
            $this->pair, $maxOrders, $deviationPercent * 100, $marketMakerProbability * 100
        ));

        // Якщо випадкове число більше за ймовірність створення ордерів, скасовуємо і відновлюємо один ордер
        if (mt_rand() / mt_getrandmax() > $marketMakerProbability) {
            $this->logger->log("[{$this->pair}] Low probability for market maker action, will update one order instead");
            
            // Оновлюємо список відкритих ордерів
            $this->updateOpenOrders();
            
            // Отримуємо списки бідів та асків
            $bids = array_filter($this->openOrders, fn($o) => $o['side'] === 2);
            $asks = array_filter($this->openOrders, fn($o) => $o['side'] === 1);
            
            if (empty($bids) && empty($asks)) {
                $this->logger->log("[{$this->pair}] No orders to update");
                return;
            }
            
            // Вибираємо випадково біди або аски
            $useAsks = (mt_rand(0, 1) === 1 && !empty($asks)) || empty($bids);
            
            if ($useAsks) {
                // Сортуємо аски за ціною (від низької до високої)
                usort($asks, fn($a, $b) => (float) $a['price'] - (float) $b['price']);
                
                // Вибираємо випадковий аск для оновлення
                $orderIndex = mt_rand(0, count($asks) - 1);
                $orderToUpdate = $asks[$orderIndex];
                
                // Скасовуємо ордер
                $this->cancelOrder($orderToUpdate['id']);
                $this->logger->log(sprintf(
                    '[%s] Cancelled ask for update: %d @ %s',
                    $this->pair,
                    $orderToUpdate['id'],
                    $orderToUpdate['price']
                ));
                
                // Створюємо новий аск з оновленою ціною
                $randBase = 0.05 + (mt_rand(0, 900) / 1000);
                $randomFactor = pow($randBase, 1/3);
                
                $askPrice = number_format(
                    $marketPrice * (1 + $deviationPercent / 2 + ($randomFactor * $deviationPercent) / 2),
                    12,
                    '.',
                    ''
                );
                $askAmount = number_format((float)$orderToUpdate['amount'], 8, '.', '');
                $this->placeLimitOrder(1, $askAmount, $askPrice);
                $this->logger->log(sprintf(
                    '[%s] Placed updated ask: %s @ %s (was @ %s, factor: %.4f)',
                    $this->pair,
                    $askAmount,
                    $askPrice,
                    $orderToUpdate['price'],
                    $randomFactor
                ));
            } else {
                // Сортуємо біди за ціною (від високої до низької)
                usort($bids, fn($a, $b) => (float) $b['price'] - (float) $a['price']);
                
                // Вибираємо випадковий бід для оновлення
                $orderIndex = mt_rand(0, count($bids) - 1);
                $orderToUpdate = $bids[$orderIndex];
                
                // Скасовуємо ордер
                $this->cancelOrder($orderToUpdate['id']);
                $this->logger->log(sprintf(
                    '[%s] Cancelled bid for update: %d @ %s',
                    $this->pair,
                    $orderToUpdate['id'],
                    $orderToUpdate['price']
                ));
                
                // Створюємо новий бід з оновленою ціною
                $randBase = 0.05 + (mt_rand(0, 900) / 1000);
                $randomFactor = pow($randBase, 1/3);
                
                $bidPrice = number_format(
                    $marketPrice * (1 - $deviationPercent / 2 + ($randomFactor * $deviationPercent) / 2),
                    12,
                    '.',
                    ''
                );
                $bidAmount = number_format((float)$orderToUpdate['amount'], 8, '.', '');
                $this->placeLimitOrder(2, $bidAmount, $bidPrice);
                $this->logger->log(sprintf(
                    '[%s] Placed updated bid: %s @ %s (was @ %s, factor: %.4f)',
                    $this->pair,
                    $bidAmount,
                    $bidPrice,
                    $orderToUpdate['price'],
                    $randomFactor
                ));
            }
            
            return;
        }

        $action = mt_rand() / mt_getrandmax();
        if ($action < 0.3 && count($currentBids) < $maxOrders) {
            $randBase = 0.05 + (mt_rand(0, 900) / 1000);
            $randomFactor = pow($randBase, 1/3);
            
            $bidPrice = number_format(
                $marketPrice * (1 - $deviationPercent / 2 + ($randomFactor * $deviationPercent) / 2),
                12,
                '.',
                '',
            );
            $bidAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.09, 8, '.', '');
            $this->placeLimitOrder(2, $bidAmount, $bidPrice);
            $this->logger->log(sprintf(
                '[%s] Placed bid: %s @ %s (factor: %.4f)',
                $this->pair, 
                $bidAmount, 
                $bidPrice,
                $randomFactor
            ));
        } elseif ($action < 0.6 && count($currentAsks) < $maxOrders) {
            $randBase = 0.05 + (mt_rand(0, 900) / 1000);
            $randomFactor = pow($randBase, 1/3);
            
            $askPrice = number_format(
                $marketPrice * (1 + $deviationPercent / 2 + ($randomFactor * $deviationPercent) / 2),
                12,
                '.',
                '',
            );
            $askAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.09, 8, '.', '');
            $this->placeLimitOrder(1, $askAmount, $askPrice);
            $this->logger->log(sprintf(
                '[%s] Placed ask: %s @ %s (factor: %.4f)',
                $this->pair, 
                $askAmount, 
                $askPrice,
                $randomFactor
            ));
        } elseif ($action < 0.8 && count($pendingOrders) > 0) {
            $this->updateOpenOrders(); // Оновлюємо список відкритих ордерів один раз перед операціями скасування
            $bids = array_filter($this->openOrders, fn($o) => $o['side'] === 2);
            $asks = array_filter($this->openOrders, fn($o) => $o['side'] === 1);
            
            if (empty($bids) && empty($asks)) {
                $this->logger->log("[{$this->pair}] No orders to cancel");
                return;
            }
            
            usort($bids, fn($a, $b) => (float) $b['price'] - (float) $a['price']);
            usort($asks, fn($a, $b) => (float) $a['price'] - (float) $b['price']);
            
            if (count($bids) > 0 && count($asks) > 0) {
                $rand = mt_rand() / mt_getrandmax();
                if ($rand < 0.5) {
                    $orderToCancel = end($bids);
                    $this->cancelOrder($orderToCancel['id']); // Cancel the lowest bid
                    $this->logger->log(
                        sprintf(
                            '[%s] Cancelled the lowest bid: %d @ %s',
                            $this->pair,
                            $orderToCancel['id'],
                            $orderToCancel['price'],
                        ),
                    );
                } else {
                    $orderToCancel = end($asks);
                    $this->cancelOrder($orderToCancel['id']); // Cancel the highest ask
                    $this->logger->log(
                        sprintf(
                            '[%s] Cancelled the highest ask: %d @ %s',
                            $this->pair,
                            $orderToCancel['id'],
                            $orderToCancel['price'],
                        ),
                    );
                }
            } elseif (count($bids) > 0) {
                $orderToCancel = end($bids);
                $this->cancelOrder($orderToCancel['id']); // Cancel the lowest bid
                $this->logger->log(
                    sprintf(
                        '[%s] Cancelled the lowest bid: %d @ %s', 
                        $this->pair, 
                        $orderToCancel['id'], 
                        $orderToCancel['price']
                    ),
                );
            } elseif (count($asks) > 0) {
                $orderToCancel = end($asks);
                $this->cancelOrder($orderToCancel['id']); // Cancel the highest ask
                $this->logger->log(
                    sprintf(
                        '[%s] Cancelled the highest ask: %d @ %s', 
                        $this->pair, 
                        $orderToCancel['id'], 
                        $orderToCancel['price']
                    ),
                );
            }
        } elseif (count($pendingOrders) > 0) {
            $this->updateOpenOrders(); // Оновлюємо список відкритих ордерів перед виконанням ринкових операцій
            $bids = array_filter($this->openOrders, fn($o) => $o['side'] === 2);
            $asks = array_filter($this->openOrders, fn($o) => $o['side'] === 1);
            usort($bids, fn($a, $b) => (float) $b['price'] - (float) $a['price']);
            usort($asks, fn($a, $b) => (float) $a['price'] - (float) $b['price']);
            if (count($bids) > 0 && count($asks) > 0) {
                if ($this->lastActionWasSell) {
                    $this->placeMarketOrder(2, $asks[0]['amount']); // Buy at the lowest ask
                    $this->logger->log(
                        sprintf(
                            '[%s] Market trade: Bought %s @ %s',
                            $this->pair,
                            $asks[0]['amount'],
                            $asks[0]['price'],
                        ),
                    );
                    $this->lastActionWasSell = false;
                } else {
                    $this->placeMarketOrder(1, $bids[0]['amount']); // Sell at the highest bid
                    $this->logger->log(
                        sprintf(
                            '[%s] Market trade: Sold %s @ %s',
                            $this->pair,
                            $bids[0]['amount'],
                            $bids[0]['price'],
                        ),
                    );
                    $this->lastActionWasSell = true;
                }
            } elseif (count($bids) > 0) {
                $this->placeMarketOrder(1, $bids[0]['amount']);
                $this->logger->log(
                    sprintf('[%s] Market trade: Sold %s @ %s', $this->pair, $bids[0]['amount'], $bids[0]['price']),
                );
            } elseif (count($asks) > 0) {
                $this->placeMarketOrder(2, $asks[0]['amount']);
                $this->logger->log(
                    sprintf('[%s] Market trade: Bought %s @ %s', $this->pair, $asks[0]['amount'], $asks[0]['price']),
                );
            }
        }
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
        // Отримання налаштувань з settings або з кореневого об'єкту для зворотної сумісності
        $deviationPercent = $this->pairConfig['settings']['price_factor'];
        $marketGap = $this->pairConfig['settings']['market_gap'];
        
        // Логування для діагностики
        $this->logger->log(sprintf('[%s] Price calculation using deviation=%.4f%%, market_gap=%.4f%%', 
            $this->pair, $deviationPercent, $marketGap));
        
        // Calculating the maximum deviation
        $maxDeviation = $marketPrice * ($deviationPercent / 100);
        
        // Використовуємо принципово інший алгоритм для рівномірного розподілу
        // 1. Генеруємо випадкове число в діапазоні [0.05, 0.95] для уникнення крайніх значень
        $randBase = 0.05 + (mt_rand(0, 900) / 1000);
        
        // 2. Використовуємо кубічний корінь для ще більш рівномірного розподілу в центрі
        $randomFactor = pow($randBase, 1/3);
        
        // 3. Розраховуємо відхилення з обмеженням
        $randomDeviation = $randomFactor * $maxDeviation;
        
        // Детальне логування розрахунків
        $this->logger->log(sprintf(
            '[%s] Price deviation details: randBase=%.4f, randomFactor=%.4f, maxDeviation=%.4f, randomDeviation=%.4f',
            $this->pair,
            $randBase,
            $randomFactor,
            $maxDeviation,
            $randomDeviation
        ));
        
        // Applying the MarketGap
        $gapAdjustment = $marketPrice * ($marketGap / 100);
        
        // Calculating the price depending on the side (buy/sell)
        $finalPrice = 0.0;
        if ($side === 1) { // Ask (sell)
            $finalPrice = $marketPrice + $randomDeviation + $gapAdjustment;
        } else { // Bid (buy)
            $finalPrice = $marketPrice - $randomDeviation - $gapAdjustment;
        }
        
        $this->logger->log(sprintf(
            '[%s] Final price calculation: marketPrice=%.6f, side=%d, finalPrice=%.6f, deviation=%.6f%%',
            $this->pair,
            $marketPrice,
            $side,
            $finalPrice,
            (($finalPrice - $marketPrice) / $marketPrice) * 100
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
        
        // Getting the current bids and asks
        $currentBids = $this->getCurrentOrderBook(2); // Bids
        $currentAsks = $this->getCurrentOrderBook(1); // Asks
        
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
    private function updateOpenOrders() {
        try {
            $this->openOrders = $this->exchangeManager->getOpenOrders($this->pair);
            $this->logger->log("[{$this->pair}] Updated open orders list, found " . count($this->openOrders) . " orders");
        } catch (Exception $e) {
            $this->logger->error("[{$this->pair}] Error updating open orders: " . $e->getMessage());
            // Додаємо логування стек трейсу для відстеження джерела помилки
            $this->logger->logStackTrace("[{$this->pair}] Stack trace for updating open orders error:");
        }
    }
}
