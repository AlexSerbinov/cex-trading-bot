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
        $minOrders = $this->pairConfig['settings']['min_orders'];
        $maxOrders = $minOrders + 1; // Derive max_orders
        $deviationPercent = $this->pairConfig['settings']['price_factor'] / 100;
        $marketGap = $this->pairConfig['settings']['market_gap'] / 100;
        
        // Ймовірність ВИКОНАННЯ РИНКОВОГО ОРДЕРА
        $marketMakerProbability = $this->pairConfig['settings']['market_maker_order_probability'] / 100;
        
        $this->logger->log(sprintf(
            '[%s] Market Order Probability: %.2f%%', 
            $this->pair, $marketMakerProbability * 100
        ));
    
        // Генеруємо випадкове число від 0 до 1
        $randomValue = mt_rand() / mt_getrandmax();
        
        // Застосовуємо market_gap до ціни (потрібно для createNewBid/Ask/updateExistingOrder)
        $gapAdjustment = $marketPrice * $marketGap;

        // Перевірка, чи виконувати ринковий ордер
        if ($randomValue <= $marketMakerProbability && $marketMakerProbability > 0) {
            $this->logger->log(sprintf(
                '[%s] Probability check PASSED (%.6f <= %.6f). Executing Market Trade.',
                $this->pair,
                $randomValue,
                $marketMakerProbability
            ));
            $this->executeMarketTrade($pendingOrders);
        } else {
            // Якщо ринковий ордер не виконується, виконуємо одну з дій з лімітними ордерами
            $this->logger->log(sprintf(
                '[%s] Probability check FAILED (%.6f > %.6f). Performing Limit Order Action.',
                $this->pair,
                $randomValue,
                $marketMakerProbability
            ));

            // Випадково вибираємо дію з лімітними ордерами
            $limitActionValue = mt_rand() / mt_getrandmax();

            if ($limitActionValue < 0.5) {
                 // 50% шанс: Оновити існуючий ордер (скасувати + створити новий на тій же стороні)
                 $this->logger->log("[{$this->pair}] Performing Limit Action: Update Existing Order");
                 $this->updateExistingOrder($marketPrice, $deviationPercent, $marketGap);
            } elseif ($limitActionValue < 0.75 && count($currentBids) < $maxOrders) {
                // 25% шанс: Створити новий Bid (якщо є місце)
                $this->logger->log("[{$this->pair}] Performing Limit Action: Create New Bid");
                $this->createNewBid($marketPrice, $deviationPercent, $gapAdjustment);
            } elseif (count($currentAsks) < $maxOrders) {
                 // 25% шанс: Створити новий Ask (якщо є місце)
                 $this->logger->log("[{$this->pair}] Performing Limit Action: Create New Ask");
                 $this->createNewAsk($marketPrice, $deviationPercent, $gapAdjustment);
            } else {
                // Fallback: якщо не вдалося створити ні бід, ні аск (бо досягли максимуму), спробуємо оновити
                 $this->logger->log("[{$this->pair}] Performing Limit Action (Fallback): Update Existing Order");
                 $this->updateExistingOrder($marketPrice, $deviationPercent, $marketGap);
            }
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
            $this->logger->log("[{$this->pair}] No orders to update");
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
        $this->logger->log("[{$this->pair}] Updating an ask order");

        // Сортуємо аски за ціною (від низької до високої)
        usort($asks, fn($a, $b) => (float) $a['price'] - (float) $b['price']);
        
        // Вибираємо випадковий аск для оновлення
        // $orderIndex = mt_rand(0, count($asks) - 1);
        // $orderToUpdate = $asks[$orderIndex];

        // Select an ask order using weighted random selection based on distance from best ask
        $bestAskPrice = $marketPrice + $gapAdjustment;
        $orderToUpdate = $this->selectOrderWeightedByDistance($asks, $bestAskPrice, false);

        if ($orderToUpdate === null) {
            $this->logger->log("[{$this->pair}] No ask order selected for update (list might be empty).");
            return;
        }
        
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
        $this->logger->log("[{$this->pair}] Updating a bid order");

        // Сортуємо біди за ціною (від високої до низької)
        usort($bids, fn($a, $b) => (float) $b['price'] - (float) $a['price']);
        
        // Вибираємо випадковий бід для оновлення
        // $orderIndex = mt_rand(0, count($bids) - 1);
        // $orderToUpdate = $bids[$orderIndex];

        // Select a bid order using weighted random selection based on distance from best bid
        $bestBidPrice = $marketPrice - $gapAdjustment;
        $orderToUpdate = $this->selectOrderWeightedByDistance($bids, $bestBidPrice, true);

        if ($orderToUpdate === null) {
            $this->logger->log("[{$this->pair}] No bid order selected for update (list might be empty).");
            return;
        }
        
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
        $this->logger->log("[{$this->pair}] Placing a new bid");

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
        $this->logger->log("[{$this->pair}] Placing a new ask");

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
     * @param float|null $marketPrice Optional market price for weighted cancellation
     * @param float|null $marketGap Optional market gap percentage for weighted cancellation
     */
    private function cancelOrder(array $pendingOrders, ?float $marketPrice = null, ?float $marketGap = null): void
    {
        if (empty($pendingOrders)) {
            $this->logger->log("[{$this->pair}] No orders to cancel.");
            return;
        }

        $orderToCancel = null;

        // Use weighted selection if market price and gap are provided
        if ($marketPrice !== null && $marketGap !== null) {
            $this->logger->log("[{$this->pair}] Attempting weighted cancellation...");
            $bids = array_filter($pendingOrders, fn($o) => $o['side'] === 2);
            $asks = array_filter($pendingOrders, fn($o) => $o['side'] === 1);

            // Decide which side to target randomly, proportional to the number of orders on each side
            $totalBids = count($bids);
            $totalAsks = count($asks);
            $totalOrders = $totalBids + $totalAsks;

            if ($totalOrders === 0) {
                $this->logger->log("[{$this->pair}] No bids or asks found in pending orders for weighted cancellation.");
                return; // Should not happen if pendingOrders is not empty, but safe check
            }

            $targetBids = ($totalBids > 0) && (($totalAsks === 0) || (mt_rand(1, $totalOrders) <= $totalBids));

            if ($targetBids) {
                $this->logger->log("[{$this->pair}] Weighted cancellation targeting Bids.");
                $bestBidPrice = $marketPrice * (1 - $marketGap);
                $orderToCancel = $this->selectOrderWeightedByDistance($bids, $bestBidPrice, true);
            } else { // Target Asks
                $this->logger->log("[{$this->pair}] Weighted cancellation targeting Asks.");
                $bestAskPrice = $marketPrice * (1 + $marketGap);
                $orderToCancel = $this->selectOrderWeightedByDistance($asks, $bestAskPrice, false);
            }

            if ($orderToCancel === null) {
                 $this->logger->log("[{$this->pair}] Weighted selection did not yield an order, falling back to random.");
            }
        }
        
        // Fallback to random selection if weighted selection was not possible or failed
        if ($orderToCancel === null) {
            $this->logger->log("[{$this->pair}] Falling back to random cancellation.");
            $orderIndex = mt_rand(0, count($pendingOrders) - 1);
            $orderToCancel = $pendingOrders[$orderIndex];
        }

        if ($orderToCancel) {
            $this->logger->log(sprintf(
                '[%s] Selected order to cancel: %d @ %s (Side: %d)',
                $this->pair, 
                $orderToCancel['id'], 
                $orderToCancel['price'],
                $orderToCancel['side']
            ));
            $this->bot->cancelOrder($orderToCancel['id']);
        } else {
            $this->logger->log("[{$this->pair}] Could not select any order to cancel.");
        }
    }

    /**
     * Execute a market trade
     * 
     * @param array $pendingOrders List of pending orders
     */
    private function executeMarketTrade(array $pendingOrders): void
    {
        $this->logger->log("[{$this->pair}] Performing a market trade");

        // Get fresh orders
        $this->bot->updateOpenOrders(); 
        $openOrders = $this->bot->getOpenOrders(); 
        $bids = array_filter($openOrders, fn($o) => $o['side'] === 2);
        $asks = array_filter($openOrders, fn($o) => $o['side'] === 1);
        usort($bids, fn($a, $b) => (float) $b['price'] - (float) $a['price']); // Highest price first
        usort($asks, fn($a, $b) => (float) $a['price'] - (float) $b['price']); // Lowest price first
        
        $bidCount = count($bids);
        $askCount = count($asks);
        $this->logger->log(sprintf("[{$this->pair}] Found %d bids and %d asks for market trade.", $bidCount, $askCount));

        // Log state BEFORE decision
        $lastActionWasSell_before = $this->bot->lastActionWasSell;
        $this->logger->log(sprintf("[{$this->pair}] State before trade decision: lastActionWasSell = %s", $lastActionWasSell_before ? 'true' : 'false'));

        if ($bidCount > 0 && $askCount > 0) {
            // Main logic branch
            if ($lastActionWasSell_before) { // If last action was SELL, now we BUY
                $this->logger->log("[{$this->pair}] Decided to BUY (hit ASK)");
                if (isset($asks[0])) { 
                    $targetOrder = $asks[0];
                    $price = (float)$targetOrder['price'];
                    $btc_amount = (float)$targetOrder['amount'];
                    // Calculate the USDT amount needed to buy the target BTC amount at the target price
                    $usdt_amount_to_spend = $btc_amount * $price;
                    // Format USDT amount appropriately (e.g., 8 decimal places)
                    $formatted_usdt_amount = number_format($usdt_amount_to_spend, 8, '.', '');

                    $this->logger->log(sprintf(
                        "[%s] Targeting ASK ID: %d, Price: %s, BTC Amount: %s. Calculated USDT to spend: %s", 
                        $this->pair, 
                        $targetOrder['id'], 
                        $targetOrder['price'], 
                        $targetOrder['amount'],
                        $formatted_usdt_amount
                    ));
                    // Send USDT amount for side=2
                    $this->bot->placeMarketOrder(2, $formatted_usdt_amount); 
                    $this->logger->log(sprintf('[%s] Market trade executed: Placed order to BUY by spending ~%s USDT @ ~%s', $this->pair, $formatted_usdt_amount, $targetOrder['price']));
                    $this->bot->lastActionWasSell = false; // Next action should be SELL
                } else {
                     $this->logger->log("[{$this->pair}] Error: No asks found despite askCount > 0. Skipping.");
                }
            } else { // If last action was BUY (or initial state), now we SELL
                $this->logger->log("[{$this->pair}] Decided to SELL (hit BID)");
                if (isset($bids[0])) {
                    $targetOrder = $bids[0];
                    $this->logger->log(sprintf("[{$this->pair}] Targeting BID ID: %d, Price: %s, Amount: %s", $targetOrder['id'], $targetOrder['price'], $targetOrder['amount']));
                    // Send BTC amount for side=1
                    $this->bot->placeMarketOrder(1, $targetOrder['amount']); 
                    $this->logger->log(sprintf('[%s] Market trade executed: Sold %s @ %s', $this->pair, $targetOrder['amount'], $targetOrder['price']));
                    $this->bot->lastActionWasSell = true; // Next action should be BUY
                } else {
                     $this->logger->log("[{$this->pair}] Error: No bids found despite bidCount > 0. Skipping.");
                }
            }
        } elseif ($bidCount > 0) { // Only bids exist
            $this->logger->log("[{$this->pair}] Decided to SELL (hit BID - only bids available)");
             if (isset($bids[0])) {
                $targetOrder = $bids[0];
                 $this->logger->log(sprintf("[{$this->pair}] Targeting BID ID: %d, Price: %s, Amount: %s", $targetOrder['id'], $targetOrder['price'], $targetOrder['amount']));
                // Send BTC amount for side=1
                $this->bot->placeMarketOrder(1, $targetOrder['amount']); 
                $this->logger->log(sprintf('[%s] Market trade executed: Sold %s @ %s', $this->pair, $targetOrder['amount'], $targetOrder['price']));
                 $this->bot->lastActionWasSell = true; // Set flag even in one-sided market
             } else {
                 $this->logger->log("[{$this->pair}] Error: No bids found despite bidCount > 0. Skipping.");
             }
        } elseif ($askCount > 0) { // Only asks exist
            $this->logger->log("[{$this->pair}] Decided to BUY (hit ASK - only asks available)");
             if (isset($asks[0])) {
                $targetOrder = $asks[0];
                $price = (float)$targetOrder['price'];
                $btc_amount = (float)$targetOrder['amount'];
                // Calculate the USDT amount needed
                $usdt_amount_to_spend = $btc_amount * $price;
                $formatted_usdt_amount = number_format($usdt_amount_to_spend, 8, '.', '');

                $this->logger->log(sprintf(
                    "[%s] Targeting ASK ID: %d, Price: %s, BTC Amount: %s. Calculated USDT to spend: %s", 
                    $this->pair, 
                    $targetOrder['id'], 
                    $targetOrder['price'], 
                    $targetOrder['amount'],
                    $formatted_usdt_amount
                ));
                // Send USDT amount for side=2
                $this->bot->placeMarketOrder(2, $formatted_usdt_amount); 
                $this->logger->log(sprintf('[%s] Market trade executed: Placed order to BUY by spending ~%s USDT @ ~%s', $this->pair, $formatted_usdt_amount, $targetOrder['price']));
                 $this->bot->lastActionWasSell = false; // Set flag even in one-sided market
             } else {
                 $this->logger->log("[{$this->pair}] Error: No asks found despite askCount > 0. Skipping.");
             }
        } else {
             $this->logger->log("[{$this->pair}] No bids or asks available to execute market trade against. Skipping.");
        }
        
        // Log state AFTER decision/update
        $lastActionWasSell_after = $this->bot->lastActionWasSell;
        $this->logger->log(sprintf("[{$this->pair}] State after trade decision: lastActionWasSell = %s", $lastActionWasSell_after ? 'true' : 'false'));
    }

    /**
     * Selects an order from a list using weighted random selection based on distance from a reference price.
     * Orders closer to the reference price have a higher probability of being selected.
     * 
     * @param array $orders List of orders (bids or asks)
     * @param float $referencePrice The price to measure distance from (e.g., best bid or best ask)
     * @param bool $isBid True if the orders are bids, false if asks (determines distance calculation)
     * @return array|null The selected order or null if the list is empty
     */
    private function selectOrderWeightedByDistance(array $orders, float $referencePrice, bool $isBid): ?array
    {
        if (empty($orders)) {
            return null;
        }

        $weightedOrders = [];
        $totalWeight = 0.0;
        $epsilon = 1e-12; // Small value to prevent division by zero and handle orders exactly at reference

        foreach ($orders as $order) {
            $price = (float)$order['price'];
            // Calculate distance: positive value indicating how far the order is from the 'inside' edge
            $distance = $isBid ? ($referencePrice - $price) : ($price - $referencePrice);
            
            // Ensure distance is non-negative (can happen with price fluctuations)
            $distance = max(0, $distance); 

            // Calculate weight: inverse square relationship to distance
            // Add epsilon to avoid division by zero and give non-zero weight to orders at reference
            $weight = 1.0 / (pow($distance, 2) + $epsilon); 
            
            // Add a small base weight to ensure even distant orders have a chance
            $baseWeight = 0.01; // Adjust this value if needed
            $weight += $baseWeight;

            $weightedOrders[] = ['order' => $order, 'weight' => $weight];
            $totalWeight += $weight;
        }
        
        if ($totalWeight <= 0) {
            // Fallback to simple random selection if all weights are zero (should not happen with baseWeight)
            $randomIndex = mt_rand(0, count($orders) - 1);
             $this->logger->log(sprintf(
                '[%s] Warning: Total weight is zero or negative in weighted selection. Falling back to random.', $this->pair
            ));
            return $orders[$randomIndex];
        }

        // Perform weighted random selection
        $randomNumber = (mt_rand() / mt_getrandmax()) * $totalWeight;
        $cumulativeWeight = 0.0;

        foreach ($weightedOrders as $weightedOrder) {
            $cumulativeWeight += $weightedOrder['weight'];
            if ($randomNumber <= $cumulativeWeight) {
                 $selectedOrder = $weightedOrder['order'];
                 $this->logger->log(sprintf(
                    '[%s] Weighted selection: chose order %d (price: %s) with weight %.6f (distance: %.6f)', 
                    $this->pair, 
                    $selectedOrder['id'], 
                    $selectedOrder['price'], 
                    $weightedOrder['weight'],
                     $isBid ? ($referencePrice - (float)$selectedOrder['price']) : ((float)$selectedOrder['price'] - $referencePrice)
                ));
                return $selectedOrder;
            }
        }

        // Fallback (should ideally not be reached if totalWeight > 0)
        $this->logger->log(sprintf(
             '[%s] Warning: Weighted selection did not pick an order. Falling back to last order.', $this->pair
         ));
        return end($weightedOrders)['order'] ?? null;
    }
} 