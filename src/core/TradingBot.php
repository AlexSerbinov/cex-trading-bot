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
        $exchange = $this->pairConfig['exchange'] ?? 'kraken';
        
        try {
            // Using the ExchangeManager to get the order book
            return $this->exchangeManager->getOrderBook($exchange, $this->pair);
        } catch (Exception $e) {
            $this->logger->error("[{$this->pair}] Unable to get the order book: " . $e->getMessage());
            
            // If we cannot get data from the API, use the mock
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
        $response = $this->apiClient->post(Config::TRADE_SERVER_URL, json_encode($body));
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
        $response = $this->apiClient->post(Config::TRADE_SERVER_URL, json_encode($body));
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
        $this->logger->log("Clearing all orders for the pair {$this->pair}...");
        
        try {
            // Getting all open orders
            $pendingOrders = $this->getPendingOrders();
            
            if (empty($pendingOrders)) {
                $this->logger->log("There are no open orders for the pair {$this->pair}");
                return;
            }
            
            // Cancelling each order
            foreach ($pendingOrders as $order) {
                $this->cancelOrder($order['id']);
                $this->logger->log("[{$this->pair}] Cancelled order: {$order['id']}");
                
                // Small delay between cancellations
                usleep(mt_rand(Config::DELAY_CLEAR_MIN * 1000, Config::DELAY_CLEAR_MAX * 1000));
            }
            
            $this->logger->log("All existing orders for the pair {$this->pair} have been cleared.");
        } catch (Exception $e) {
            $this->logger->error("[{$this->pair}] Error clearing orders: " . $e->getMessage());
        }
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
        $response = $this->apiClient->post(Config::TRADE_SERVER_URL, json_encode($body));
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
     * Cancels an order.
     *
     * @param int $orderId Order ID to cancel
     * @return mixed
     * @throws RuntimeException If the request fails
     */
    private function cancelOrder(int $orderId)
    {
        $body = [
            'method' => 'order.cancel',
            'params' => [Config::BOT_USER_ID, $this->pair, $orderId],
            'id' => 1,
        ];
        $response = $this->apiClient->post(Config::TRADE_SERVER_URL, json_encode($body));
        $data = json_decode($response, true);

        if ($data['error'] !== null) {
            $this->logger->log(
                sprintf(
                    '[%s] Result of cancelling order %d: %s',
                    $this->pair,
                    $orderId,
                    json_encode($data, JSON_PRETTY_PRINT),
                ),
            );
        }

        return $data;
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
        $response = $this->apiClient->post(Config::TRADE_SERVER_URL, json_encode($body));
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
        $minOrders = $this->pairConfig['min_orders'];

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
     * Initializes the order book based on external data.
     */
    private function initializeOrderBook(array $orderBook): void
    {
        $botBalance = $this->pairConfig['bot_balance'];
        $scaledBids = $this->scaleVolumes($orderBook['bids'], $botBalance / 2, true); // Half balance for bids
        $scaledAsks = $this->scaleVolumes($orderBook['asks'], $botBalance / 2, false); // Half balance for asks
        $minOrders = $this->pairConfig['min_orders'];

        foreach (array_slice($scaledBids, 0, $minOrders) as $bid) {
            $this->placeLimitOrder($bid['side'], $bid['amount'], $bid['price']);
            $this->logger->log(sprintf('[%s] Initialized bid: %s @ %s', $this->pair, $bid['amount'], $bid['price']));
            $this->randomDelay(Config::DELAY_INIT_MIN, Config::DELAY_INIT_MAX);
        }

        foreach (array_slice($scaledAsks, 0, $minOrders) as $ask) {
            $this->placeLimitOrder($ask['side'], $ask['amount'], $ask['price']);
            $this->logger->log(sprintf('[%s] Initialized ask: %s @ %s', $this->pair, $ask['amount'], $ask['price']));
            $this->randomDelay(Config::DELAY_INIT_MIN, Config::DELAY_INIT_MAX);
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
        $minOrders = $this->pairConfig['min_orders'];
        $maxOrders = $this->pairConfig['max_orders'];
        $deviationPercent = $this->pairConfig['price_deviation_percent'] / 100;

        // Add bids if there are too few
        while (count($currentBids) < $minOrders) {
            $bidPrice = number_format(
                $marketPrice * (1 - $deviationPercent + (mt_rand() / mt_getrandmax()) * $deviationPercent),
                6,
                '.',
                '',
            );
            $bidAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.19, 8, '.', '');
            $this->placeLimitOrder(2, $bidAmount, $bidPrice);
            $this->logger->log(
                sprintf(
                    '[%s] Added bid to achieve %d-%d: %s @ %s',
                    $this->pair,
                    $minOrders,
                    $maxOrders,
                    $bidAmount,
                    $bidPrice,
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
            $askPrice = number_format(
                $marketPrice * (1 + $deviationPercent + (mt_rand() / mt_getrandmax()) * $deviationPercent),
                6,
                '.',
                '',
            );
            $askAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.19, 8, '.', '');
            $this->placeLimitOrder(1, $askAmount, $askPrice);
            $this->logger->log(
                sprintf(
                    '[%s] Added ask to achieve %d-%d: %s @ %s',
                    $this->pair,
                    $minOrders,
                    $maxOrders,
                    $askAmount,
                    $askPrice,
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
            $bids = array_filter($pendingOrders, fn($o) => $o['side'] === 2);
            usort($bids, fn($a, $b) => (float) $a['price'] - (float) $b['price']); // Sort by lowest prices
            for ($i = 0; $i < $bidsToCancel && count($bids) > 0; $i++) {
                $this->cancelOrder($bids[$i]['id']);
                $this->logger->log(
                    sprintf('[%s] Cancelled excess bid: %d @ %s', $this->pair, $bids[$i]['id'], $bids[$i]['price']),
                );
                $this->randomDelay(Config::DELAY_MAINTAIN_MIN, Config::DELAY_MAINTAIN_MAX);
            }
        }

        // Cancel excess asks (more than max)
        if (count($currentAsks) > $maxOrders) {
            $asksToCancel = count($currentAsks) - $maxOrders;
            $asks = array_filter($pendingOrders, fn($o) => $o['side'] === 1);
            usort($asks, fn($a, $b) => (float) $b['price'] - (float) $a['price']); // Sort by highest prices
            for ($i = 0; $i < $asksToCancel && count($asks) > 0; $i++) {
                $this->cancelOrder($asks[$i]['id']);
                $this->logger->log(
                    sprintf('[%s] Cancelled excess ask: %d @ %s', $this->pair, $asks[$i]['id'], $asks[$i]['price']),
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
        $maxOrders = $this->pairConfig['max_orders'];
        $deviationPercent = $this->pairConfig['price_deviation_percent'] / 100;

        $action = mt_rand() / mt_getrandmax();
        if ($action < 0.3 && count($currentBids) < $maxOrders) {
            $bidPrice = number_format(
                $marketPrice * (1 - $deviationPercent / 2 + ((mt_rand() / mt_getrandmax()) * $deviationPercent) / 2),
                6,
                '.',
                '',
            );
            $bidAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.09, 8, '.', '');
            $this->placeLimitOrder(2, $bidAmount, $bidPrice);
            $this->logger->log(sprintf('[%s] Placed bid: %s @ %s', $this->pair, $bidAmount, $bidPrice));
        } elseif ($action < 0.6 && count($currentAsks) < $maxOrders) {
            $askPrice = number_format(
                $marketPrice * (1 + $deviationPercent / 2 + ((mt_rand() / mt_getrandmax()) * $deviationPercent) / 2),
                6,
                '.',
                '',
            );
            $askAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.09, 8, '.', '');
            $this->placeLimitOrder(1, $askAmount, $askPrice);
            $this->logger->log(sprintf('[%s] Placed ask: %s @ %s', $this->pair, $askAmount, $askPrice));
        } elseif ($action < 0.8 && count($pendingOrders) > 0) {
            $bids = array_filter($pendingOrders, fn($o) => $o['side'] === 2);
            $asks = array_filter($pendingOrders, fn($o) => $o['side'] === 1);
            usort($bids, fn($a, $b) => (float) $b['price'] - (float) $a['price']);
            usort($asks, fn($a, $b) => (float) $a['price'] - (float) $b['price']);
            if (count($bids) > 0 && count($asks) > 0) {
                $rand = mt_rand() / mt_getrandmax();
                if ($rand < 0.5) {
                    $this->cancelOrder(end($bids)['id']); // Cancel the lowest bid (not the top one)
                    $this->logger->log(
                        sprintf(
                            '[%s] Cancelled the lowest bid: %d @ %s',
                            $this->pair,
                            end($bids)['id'],
                            end($bids)['price'],
                        ),
                    );
                } else {
                    $this->cancelOrder(end($asks)['id']); // Cancel the highest ask (not the top one)
                    $this->logger->log(
                        sprintf(
                            '[%s] Cancelled the highest ask: %d @ %s',
                            $this->pair,
                            end($asks)['id'],
                            end($asks)['price'],
                        ),
                    );
                }
            } elseif (count($bids) > 0) {
                $this->cancelOrder(end($bids)['id']); // Cancel the lowest bid
                $this->logger->log(
                    sprintf('[%s] Cancelled the lowest bid: %d @ %s', $this->pair, end($bids)['id'], end($bids)['price']),
                );
            } elseif (count($asks) > 0) {
                $this->cancelOrder(end($asks)['id']); // Cancel the highest ask
                $this->logger->log(
                    sprintf('[%s] Cancelled the highest ask: %d @ %s', $this->pair, end($asks)['id'], end($asks)['price']),
                );
            }
        } elseif (count($pendingOrders) > 0) {
            $bids = array_filter($pendingOrders, fn($o) => $o['side'] === 2);
            $asks = array_filter($pendingOrders, fn($o) => $o['side'] === 1);
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
        // Getting the percentage deviation from the configuration
        $deviationPercent = $this->pairConfig['price_deviation_percent'] ?? 0.01;
        $marketGap = $this->pairConfig['market_gap'] ?? 0.05;
        
        // Calculating the maximum deviation
        $maxDeviation = $marketPrice * ($deviationPercent / 100);
        
        // Generating a random deviation within the maximum
        $randomDeviation = mt_rand(0, 1000) / 1000 * $maxDeviation;
        
        // Applying the MarketGap
        $gapAdjustment = $marketPrice * ($marketGap / 100);
        
        // Calculating the price depending on the side (buy/sell)
        if ($side === 1) { // Ask (sell)
            return $marketPrice + $randomDeviation + $gapAdjustment;
        } else { // Bid (buy)
            return $marketPrice - $randomDeviation - $gapAdjustment;
        }
    }

    /**
     * Maintains orders based on the order book and current orders
     */
    private function maintainOrders(array $orderBook, array $pendingOrders): void
    {
        // Calculating the market price
        $marketPrice = $this->calculateMarketPrice($orderBook);
        
        // Getting the market_gap value
        $marketGap = $this->pairConfig['market_gap'] ?? 0.05;
        $gapAdjustment = $marketPrice * ($marketGap / 100);
        
        // Applying the market_gap to the best prices
        $bestBidPrice = $orderBook['bids'][0][0] - $gapAdjustment;
        $bestAskPrice = $orderBook['asks'][0][0] + $gapAdjustment;
        
        // Getting the current bids and asks
        $currentBids = $this->getCurrentOrderBook(2); // Bids
        $currentAsks = $this->getCurrentOrderBook(1); // Asks
        
        $this->logger->log(
            sprintf(
                '[%s] Market price: %.6f, best bid = %.6f, best ask = %.6f',
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
}
