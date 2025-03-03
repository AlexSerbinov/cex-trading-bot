<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/MockOrderBook.php';

/**
 * TradingBot - an automated bot for simulating trades on a cryptocurrency exchange.
 */
class TradingBot
{
    private bool $lastActionWasSell = false;
    private ApiClient $apiClient;
    private Logger $logger;
    private MockOrderBook $mockOrderBook;
    private string $pair;
    private array $pairConfig;
    private bool $initialized = false;

    /**
     * Constructor for TradingBot.
     *
     * @param string $pair Trading pair for this bot instance
     */
    public function __construct(string $pair)
    {
        $this->pair = $pair;
        $this->pairConfig = Config::getPairConfig($pair);
        $this->apiClient = new ApiClient();
        $this->logger = new Logger();
        $this->mockOrderBook = new MockOrderBook();
    }

    /**
     * Initializes the bot by clearing orders and setting up initial order book
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->logger->log("Ініціалізація бота для пари {$this->pair}...");
        $this->clearAllOrders();
        $this->logger->log("Всі існуючі ордери для пари {$this->pair} очищено.");
        $this->initializeOrderBook();
        $this->logger->log("Книгу ордерів для пари {$this->pair} ініціалізовано.");
        $this->initialized = true;
    }

    /**
     * Runs a single cycle of the bot operation
     */
    public function runSingleCycle(): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        try {
            $externalOrderBook = $this->getExternalOrderBook();
            $pendingOrders = $this->getPendingOrders();
            $currentBids = $this->getCurrentOrderBook(2); // Bids
            $currentAsks = $this->getCurrentOrderBook(1); // Asks

            $marketPrice = $this->calculateMarketPrice($externalOrderBook);
            $this->logger->log(
                sprintf(
                    '[%s] Ринкова ціна: %.6f, найкращий бід = %.6f, найкращий аск = %.6f',
                    $this->pair,
                    $marketPrice,
                    $externalOrderBook['bids'][0][0],
                    $externalOrderBook['asks'][0][0],
                ),
            );

            $this->logger->log(
                sprintf(
                    '[%s] Поточні біди: %d, Поточні аски: %d, Відкриті ордери: %d',
                    $this->pair,
                    count($currentBids),
                    count($currentAsks),
                    count($pendingOrders),
                ),
            );

            $this->maintainOrderCount($currentBids, $currentAsks, $marketPrice, $pendingOrders);

            $this->performRandomAction($currentBids, $currentAsks, $pendingOrders, $marketPrice);
        } catch (Exception $e) {
            $this->logger->error("[{$this->pair}] Помилка: " . $e->getMessage());
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
                $this->logger->error("[{$this->pair}] Помилка: " . $e->getMessage());
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
        // Використовуємо мок для тестування, якщо потрібно
        if (defined('USE_MOCK_DATA') && USE_MOCK_DATA) {
            return $this->mockOrderBook->getMockOrderBook($this->pair);
        }

        $maxRetries = 1000;
        $retryCount = 0;
        $apiUrl = $this->pairConfig['external_api_url'];

        while ($retryCount < $maxRetries) {
            try {
                $response = $this->apiClient->get($apiUrl);
                $data = json_decode($response, true);

                // Адаптуємо парсинг відповіді залежно від пари
                $pairSymbol = str_replace('_', '', $this->pair);

                if (!isset($data['result'][$pairSymbol])) {
                    throw new RuntimeException(
                        "Не вдалося отримати книгу ордерів для пари {$this->pair}: Некоректна відповідь",
                    );
                }

                return [
                    'bids' => $data['result'][$pairSymbol]['bids'],
                    'asks' => $data['result'][$pairSymbol]['asks'],
                ];
            } catch (RuntimeException $e) {
                $retryCount++;
                if ($retryCount === $maxRetries) {
                    throw $e;
                }
                $this->logger->error(
                    sprintf('[%s] Спроба %d/%d для API: %s', $this->pair, $retryCount, $maxRetries, $e->getMessage()),
                );
                $this->randomDelay(Config::DELAY_RUN_MIN, Config::DELAY_RUN_MAX); // Затримка перед повторною спробою
            }
        }
        throw new RuntimeException("Досягнуто максимальної кількості спроб для API пари {$this->pair}");
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
                    '[%s] Відповідь API для side=%d: %s',
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
                '[%s] Неочікувана структура відповіді для side=%d: %s',
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
                    '[%s] Відповідь API для відкритих ордерів: %s',
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
    private function clearAllOrders(): void
    {
        $orders = $this->getPendingOrders();
        foreach ($orders as $order) {
            $this->cancelOrder($order['id']);
            $this->logger->log(sprintf('[%s] Скасовано ордер: %d', $this->pair, $order['id']));
            $this->randomDelay(Config::DELAY_CLEAR_MIN, Config::DELAY_CLEAR_MAX);
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
                    '[%s] Результат розміщення ордеру для side=%d: %s',
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
                    '[%s] Результат скасування ордеру %d: %s',
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

        // $this->logger->log(
        //     sprintf(
        //         '[%s] Результат ринкового ордеру для side=%d: %s',
        //         $this->pair,
        //         $side,
        //         json_encode($data, JSON_PRETTY_PRINT),
        //     ),
        // );

        return true; // Симуляція успішного виконання
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
    private function initializeOrderBook(): void
    {
        $externalOrderBook = $this->getExternalOrderBook();
        $botBalance = $this->pairConfig['bot_balance'];
        $scaledBids = $this->scaleVolumes($externalOrderBook['bids'], $botBalance / 2, true); // Half balance for bids
        $scaledAsks = $this->scaleVolumes($externalOrderBook['asks'], $botBalance / 2, false); // Half balance for asks
        $minOrders = $this->pairConfig['min_orders'];

        foreach (array_slice($scaledBids, 0, $minOrders) as $bid) {
            $this->placeLimitOrder($bid['side'], $bid['amount'], $bid['price']);
            $this->logger->log(sprintf('[%s] Ініціалізовано бід: %s @ %s', $this->pair, $bid['amount'], $bid['price']));
            $this->randomDelay(Config::DELAY_INIT_MIN, Config::DELAY_INIT_MAX);
        }

        foreach (array_slice($scaledAsks, 0, $minOrders) as $ask) {
            $this->placeLimitOrder($ask['side'], $ask['amount'], $ask['price']);
            $this->logger->log(sprintf('[%s] Ініціалізовано аск: %s @ %s', $this->pair, $ask['amount'], $ask['price']));
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
                    '[%s] Додано бід для досягнення %d-%d: %s @ %s',
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
                    '[%s] Додано аск для досягнення %d-%d: %s @ %s',
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
                    sprintf('[%s] Скасовано зайвий бід: %d @ %s', $this->pair, $bids[$i]['id'], $bids[$i]['price']),
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
                    sprintf('[%s] Скасовано зайвий аск: %d @ %s', $this->pair, $asks[$i]['id'], $asks[$i]['price']),
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
            $this->logger->log(sprintf('[%s] Розміщено бід: %s @ %s', $this->pair, $bidAmount, $bidPrice));
        } elseif ($action < 0.6 && count($currentAsks) < $maxOrders) {
            $askPrice = number_format(
                $marketPrice * (1 + $deviationPercent / 2 + ((mt_rand() / mt_getrandmax()) * $deviationPercent) / 2),
                6,
                '.',
                '',
            );
            $askAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.09, 8, '.', '');
            $this->placeLimitOrder(1, $askAmount, $askPrice);
            $this->logger->log(sprintf('[%s] Розміщено аск: %s @ %s', $this->pair, $askAmount, $askPrice));
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
                            '[%s] Скасовано нижній бід: %d @ %s',
                            $this->pair,
                            end($bids)['id'],
                            end($bids)['price'],
                        ),
                    );
                } else {
                    $this->cancelOrder(end($asks)['id']); // Cancel the highest ask (not the top one)
                    $this->logger->log(
                        sprintf(
                            '[%s] Скасовано нижній аск: %d @ %s',
                            $this->pair,
                            end($asks)['id'],
                            end($asks)['price'],
                        ),
                    );
                }
            } elseif (count($bids) > 0) {
                $this->cancelOrder(end($bids)['id']); // Cancel the lowest bid
                $this->logger->log(
                    sprintf('[%s] Скасовано нижній бід: %d @ %s', $this->pair, end($bids)['id'], end($bids)['price']),
                );
            } elseif (count($asks) > 0) {
                $this->cancelOrder(end($asks)['id']); // Cancel the highest ask
                $this->logger->log(
                    sprintf('[%s] Скасовано нижній аск: %d @ %s', $this->pair, end($asks)['id'], end($asks)['price']),
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
                            '[%s] Ринкова угода: Куплено %s @ %s',
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
                            '[%s] Ринкова угода: Продано %s @ %s',
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
                    sprintf('[%s] Ринкова угода: Продано %s @ %s', $this->pair, $bids[0]['amount'], $bids[0]['price']),
                );
            } elseif (count($asks) > 0) {
                $this->placeMarketOrder(2, $asks[0]['amount']);
                $this->logger->log(
                    sprintf('[%s] Ринкова угода: Куплено %s @ %s', $this->pair, $asks[0]['amount'], $asks[0]['price']),
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
}
