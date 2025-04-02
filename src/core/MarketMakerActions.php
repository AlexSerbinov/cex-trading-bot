<?php

declare(strict_types=1);

/**
 * Market Maker Actions for TradingBot
 * 
 * Contains helper methods for performing market maker actions
 */
class MarketMakerActions
{
    private TradingBot $bot;
    private Logger $logger;
    private string $pair;
    private array $pairConfig;

    /**
     * Constructor
     * 
     * @param TradingBot $bot Reference to the parent TradingBot instance
     * @param Logger $logger Logger instance
     * @param string $pair Trading pair
     * @param array $pairConfig Configuration for the pair
     */
    public function __construct(
        TradingBot $bot,
        Logger $logger,
        string $pair,
        array $pairConfig
    ) {
        $this->bot = $bot;
        $this->logger = $logger;
        $this->pair = $pair;
        $this->pairConfig = $pairConfig;
    }

    /**
     * Perform a random action based on market maker probability
     * 
     * @param array $currentBids Current bids
     * @param array $currentAsks Current asks
     * @param array $pendingOrders Open orders
     * @param float $marketPrice Market price
     */
    public function performRandomAction(
        array &$currentBids,
        array &$currentAsks,
        array $pendingOrders,
        float $marketPrice,
    ): void {
        $maxOrders = $this->pairConfig['settings']['max_orders'];
        $deviationPercent = $this->pairConfig['settings']['price_factor'] / 100;
        $marketGap = $this->pairConfig['settings']['market_gap'] / 100;
        
        // Ймовірність створення ордерів маркет-мейкера
        $marketMakerProbability = $this->pairConfig['settings']['market_maker_order_probability'] / 100;
        
        $this->logger->log("[{$this->pair}] =0=0=0 marketMakerProbability: " . $marketMakerProbability);
    
        $this->logger->log(sprintf(
            '[%s] Performing random actions with max_orders=%d, deviation=%.4f%%, market_gap=%.4f%%, probability=%.2f', 
            $this->pair, $maxOrders, $deviationPercent * 100, $marketGap * 100, $marketMakerProbability * 100
        ));
    
        // Генеруємо випадкове число від 0 до 1
        $randomValue = mt_rand() / mt_getrandmax();
        
        $this->logger->log(sprintf(
            '[%s] =0=0=0 Probability check: randomValue=%.6f, marketMakerProbability=%.6f',
            $this->pair,
            $randomValue,
            $marketMakerProbability
        ));
    
        // Якщо випадкове число більше за ймовірність створення ордерів маркет-мейкера
        // або якщо ймовірність дорівнює 0, завжди скасовуємо ордер і створюємо новий
        if ($randomValue > $marketMakerProbability || $marketMakerProbability <= 0) {
            $this->logger->log("[{$this->pair}] =0=0=0 Low probability for market maker action, will update one order instead");
            $this->updateExistingOrder($marketPrice, $deviationPercent, $marketGap);
            return;
        }
        
        // Якщо ми тут, то виконуємо дію маркет-мейкера
        // Застосовуємо market_gap до ціни
        $gapAdjustment = $marketPrice * $marketGap;
    
        $action = mt_rand() / mt_getrandmax();
        $this->logger->log(sprintf(
            '[%s] =0=0=0 Market maker action selected: action=%.6f',
            $this->pair,
            $action
        ));
    
        if ($action < 0.3 && count($currentBids) < $maxOrders) {
            $this->createNewBid($marketPrice, $deviationPercent, $gapAdjustment);
        } elseif ($action < 0.6 && count($currentAsks) < $maxOrders) {
            $this->createNewAsk($marketPrice, $deviationPercent, $gapAdjustment);
        } elseif ($action < 0.8 && count($pendingOrders) > 0) {
            $this->cancelOrder($pendingOrders);
            
            // При використанні маркер-мейкера, після скасування ордера треба створити новий
            $newAction = mt_rand() / mt_getrandmax();
            if ($newAction < 0.5) {
                $this->createNewBid($marketPrice, $deviationPercent, $gapAdjustment);
            } else {
                $this->createNewAsk($marketPrice, $deviationPercent, $gapAdjustment);
            }
        } else {
            $this->executeMarketTrade($pendingOrders);
        }
    }

    /**
     * Update an existing order
     * 
     * @param float $marketPrice Market price
     * @param float $deviationPercent Price deviation percentage
     * @param float $marketGap Market gap
     */
    private function updateExistingOrder(float $marketPrice, float $deviationPercent, float $marketGap): void
    {
        // Оновлюємо список відкритих ордерів
        $this->bot->updateOpenOrders();
        
        // Отримуємо списки бідів та асків
        $openOrders = $this->bot->getOpenOrders();
        $bids = array_filter($openOrders, fn($o) => $o['side'] === 2);
        $asks = array_filter($openOrders, fn($o) => $o['side'] === 1);
        
        if (empty($bids) && empty($asks)) {
            $this->logger->log("[{$this->pair}] =0=0=0 No orders to update");
            return;
        }
        
        // Вибираємо випадково біди або аски
        $useAsks = (mt_rand(0, 1) === 1 && !empty($asks)) || empty($bids);
        
        // Застосовуємо market_gap до ціни
        $gapAdjustment = $marketPrice * $marketGap;
        
        if ($useAsks) {
            $this->updateAskOrder($asks, $marketPrice, $deviationPercent, $gapAdjustment);
        } else {
            $this->updateBidOrder($bids, $marketPrice, $deviationPercent, $gapAdjustment);
        }
    }

    /**
     * Update an ask order
     * 
     * @param array $asks List of ask orders
     * @param float $marketPrice Market price
     * @param float $deviationPercent Price deviation percentage
     * @param float $gapAdjustment Gap adjustment
     */
    private function updateAskOrder(array $asks, float $marketPrice, float $deviationPercent, float $gapAdjustment): void
    {
        $this->logger->log("[{$this->pair}] =0=0=0 Updating an ask order");

        // Сортуємо аски за ціною (від низької до високої)
        usort($asks, fn($a, $b) => (float) $a['price'] - (float) $b['price']);
        
        // Вибираємо випадковий аск для оновлення
        $orderIndex = mt_rand(0, count($asks) - 1);
        $orderToUpdate = $asks[$orderIndex];
        
        // Скасовуємо ордер
        $this->bot->cancelOrder($orderToUpdate['id']);
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
            $marketPrice * (1 + $deviationPercent * $randomFactor) + $gapAdjustment,
            12,
            '.',
            ''
        );
        $askAmount = number_format((float)$orderToUpdate['amount'], 8, '.', '');
        $this->bot->placeLimitOrder(1, $askAmount, $askPrice);
        $this->logger->log(sprintf(
            '[%s] Placed updated ask: %s @ %s (was @ %s, factor: %.4f, gap: %.6f)',
            $this->pair,
            $askAmount,
            $askPrice,
            $orderToUpdate['price'],
            $randomFactor,
            $gapAdjustment
        ));
    }

    /**
     * Update a bid order
     * 
     * @param array $bids List of bid orders
     * @param float $marketPrice Market price
     * @param float $deviationPercent Price deviation percentage
     * @param float $gapAdjustment Gap adjustment
     */
    private function updateBidOrder(array $bids, float $marketPrice, float $deviationPercent, float $gapAdjustment): void
    {
        $this->logger->log("[{$this->pair}] =0=0=0 Updating a bid order");

        // Сортуємо біди за ціною (від високої до низької)
        usort($bids, fn($a, $b) => (float) $b['price'] - (float) $a['price']);
        
        // Вибираємо випадковий бід для оновлення
        $orderIndex = mt_rand(0, count($bids) - 1);
        $orderToUpdate = $bids[$orderIndex];
        
        // Скасовуємо ордер
        $this->bot->cancelOrder($orderToUpdate['id']);
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
            $marketPrice * (1 - $deviationPercent * $randomFactor) - $gapAdjustment,
            12,
            '.',
            ''
        );
        $bidAmount = number_format((float)$orderToUpdate['amount'], 8, '.', '');
        $this->bot->placeLimitOrder(2, $bidAmount, $bidPrice);
        $this->logger->log(sprintf(
            '[%s] Placed updated bid: %s @ %s (was @ %s, factor: %.4f, gap: %.6f)',
            $this->pair,
            $bidAmount,
            $bidPrice,
            $orderToUpdate['price'],
            $randomFactor,
            $gapAdjustment
        ));
    }

    /**
     * Create a new bid
     * 
     * @param float $marketPrice Market price
     * @param float $deviationPercent Price deviation percentage
     * @param float $gapAdjustment Gap adjustment
     */
    private function createNewBid(float $marketPrice, float $deviationPercent, float $gapAdjustment): void
    {
        $this->logger->log("[{$this->pair}] =0=0=0 Placing a new bid");

        $randBase = 0.05 + (mt_rand(0, 900) / 1000);
        $randomFactor = pow($randBase, 1/3);
        
        $bidPrice = number_format(
            $marketPrice * (1 - $deviationPercent * $randomFactor) - $gapAdjustment,
            12,
            '.',
            ''
        );
        $bidAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.09, 8, '.', '');
        $this->bot->placeLimitOrder(2, $bidAmount, $bidPrice);
        $this->logger->log(sprintf(
            '[%s] Placed bid: %s @ %s (factor: %.4f, gap: %.6f)',
            $this->pair, 
            $bidAmount, 
            $bidPrice,
            $randomFactor,
            $gapAdjustment
        ));
    }

    /**
     * Create a new ask
     * 
     * @param float $marketPrice Market price
     * @param float $deviationPercent Price deviation percentage
     * @param float $gapAdjustment Gap adjustment
     */
    private function createNewAsk(float $marketPrice, float $deviationPercent, float $gapAdjustment): void
    {
        $this->logger->log("[{$this->pair}] =0=0=0 Placing a new ask");

        $randBase = 0.05 + (mt_rand(0, 900) / 1000);
        $randomFactor = pow($randBase, 1/3);
        
        $askPrice = number_format(
            $marketPrice * (1 + $deviationPercent * $randomFactor) + $gapAdjustment,
            12,
            '.',
            ''
        );
        $askAmount = number_format(0.01 + (mt_rand() / mt_getrandmax()) * 0.09, 8, '.', '');
        $this->bot->placeLimitOrder(1, $askAmount, $askPrice);
        $this->logger->log(sprintf(
            '[%s] Placed ask: %s @ %s (factor: %.4f, gap: %.6f)',
            $this->pair, 
            $askAmount, 
            $askPrice,
            $randomFactor,
            $gapAdjustment
        ));
    }

    /**
     * Cancel an order
     * 
     * @param array $pendingOrders List of pending orders
     */
    private function cancelOrder(array $pendingOrders): void
    {
        $this->logger->log("[{$this->pair}] =0=0=0 Cancelling an order");

        $this->bot->updateOpenOrders(); // Оновлюємо список відкритих ордерів один раз перед операціями скасування
        $openOrders = $this->bot->getOpenOrders();
        $bids = array_filter($openOrders, fn($o) => $o['side'] === 2);
        $asks = array_filter($openOrders, fn($o) => $o['side'] === 1);
        
        if (empty($bids) && empty($asks)) {
            $this->logger->log("[{$this->pair}] =0=0=0 No orders to cancel");
            return;
        }
        
        usort($bids, fn($a, $b) => (float) $b['price'] - (float) $a['price']);
        usort($asks, fn($a, $b) => (float) $a['price'] - (float) $b['price']);
        
        if (count($bids) > 0 && count($asks) > 0) {
            $rand = mt_rand() / mt_getrandmax();
            if ($rand < 0.5) {
                $this->logger->log("[{$this->pair}] =0=0=0 Cancelling the lowest bid");
                $orderToCancel = end($bids);
                $this->bot->cancelOrder($orderToCancel['id']); // Cancel the lowest bid
                $this->logger->log(
                    sprintf(
                        '[%s] Cancelled the lowest bid: %d @ %s',
                        $this->pair,
                        $orderToCancel['id'],
                        $orderToCancel['price'],
                    ),
                );
            } else {
                $this->logger->log("[{$this->pair}] =0=0=0 Cancelling the highest ask");
                $orderToCancel = end($asks);
                $this->bot->cancelOrder($orderToCancel['id']); // Cancel the highest ask
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
            $this->logger->log("[{$this->pair}] =0=0=0 Cancelling the lowest bid (no asks available)");
            $orderToCancel = end($bids);
            $this->bot->cancelOrder($orderToCancel['id']); // Cancel the lowest bid
            $this->logger->log(
                sprintf(
                    '[%s] Cancelled the lowest bid: %d @ %s', 
                    $this->pair, 
                    $orderToCancel['id'], 
                    $orderToCancel['price']
                ),
            );
        } elseif (count($asks) > 0) {
            $this->logger->log("[{$this->pair}] =0=0=0 Cancelling the highest ask (no bids available)");
            $orderToCancel = end($asks);
            $this->bot->cancelOrder($orderToCancel['id']); // Cancel the highest ask
            $this->logger->log(
                sprintf(
                    '[%s] Cancelled the highest ask: %d @ %s', 
                    $this->pair, 
                    $orderToCancel['id'], 
                    $orderToCancel['price']
                ),
            );
        }
    }

    /**
     * Execute a market trade
     * 
     * @param array $pendingOrders List of pending orders
     */
    private function executeMarketTrade(array $pendingOrders): void
    {
        $this->logger->log("[{$this->pair}] =0=0=0 Performing a market trade");

        $this->bot->updateOpenOrders(); // Оновлюємо список відкритих ордерів перед виконанням ринкових операцій
        $openOrders = $this->bot->getOpenOrders();
        $bids = array_filter($openOrders, fn($o) => $o['side'] === 2);
        $asks = array_filter($openOrders, fn($o) => $o['side'] === 1);
        usort($bids, fn($a, $b) => (float) $b['price'] - (float) $a['price']);
        usort($asks, fn($a, $b) => (float) $a['price'] - (float) $b['price']);
        
        if (count($bids) > 0 && count($asks) > 0) {
            if ($this->bot->lastActionWasSell) {
                $this->logger->log("[{$this->pair}] =0=0=0 Buying at the lowest ask");
                $this->bot->placeMarketOrder(2, $asks[0]['amount']); // Buy at the lowest ask
                $this->logger->log(
                    sprintf(
                        '[%s] Market trade: Bought %s @ %s',
                        $this->pair,
                        $asks[0]['amount'],
                        $asks[0]['price'],
                    ),
                );
                $this->bot->lastActionWasSell = false;
            } else {
                $this->logger->log("[{$this->pair}] =0=0=0 Selling at the highest bid");
                $this->bot->placeMarketOrder(1, $bids[0]['amount']); // Sell at the highest bid
                $this->logger->log(
                    sprintf(
                        '[%s] Market trade: Sold %s @ %s',
                        $this->pair,
                        $bids[0]['amount'],
                        $bids[0]['price'],
                    ),
                );
                $this->bot->lastActionWasSell = true;
            }
        } elseif (count($bids) > 0) {
            $this->logger->log("[{$this->pair}] =0=0=0 Selling at the highest bid (no asks available)");
            $this->bot->placeMarketOrder(1, $bids[0]['amount']);
            $this->logger->log(
                sprintf('[%s] Market trade: Sold %s @ %s', $this->pair, $bids[0]['amount'], $bids[0]['price']),
            );
        } elseif (count($asks) > 0) {
            $this->logger->log("[{$this->pair}] =0=0=0 Buying at the lowest ask (no bids available)");
            $this->bot->placeMarketOrder(2, $asks[0]['amount']);
            $this->logger->log(
                sprintf('[%s] Market trade: Bought %s @ %s', $this->pair, $asks[0]['amount'], $asks[0]['price']),
            );
        }
    }
} 