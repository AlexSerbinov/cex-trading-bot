#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use React\EventLoop\Loop;
use React\Http\Browser;
use React\Promise\Promise;
use Psr\Http\Message\ResponseInterface;

class AsyncOrderCreation
{
    private string $tradeServerUrl;
    private Browser $browser;

    public function __construct()
    {
        $this->tradeServerUrl = Config::getTradeServerUrl();
        $this->browser = new Browser();
    }

    /**
     * Asynchronously create a limit order
     */
    private function createLimitOrder(string $pair, int $side, string $amount, string $price): Promise
    {
        $body = [
            'method' => 'order.put_limit',
            'params' => [
                Config::BOT_USER_ID,
                $pair,
                $side,
                $amount,
                $price,
                Config::TAKER_FEE,
                Config::MAKER_FEE,
                Config::ORDER_SOURCE,
            ],
            'id' => 1,
        ];

        return $this->browser->post($this->tradeServerUrl, [
            'Content-Type' => 'application/json'
        ], json_encode($body))
        ->then(
            function (ResponseInterface $response) use ($price, $amount, $side) {
                $result = json_decode((string) $response->getBody(), true);
                $sideStr = $side === 1 ? "sell" : "buy";
                echo "✅ Order created for {$sideStr}: {$amount} @ {$price}\n";
                return $result;
            },
            function (\Exception $e) use ($price, $amount) {
                echo "❌ Error creating order {$amount} @ {$price}: " . $e->getMessage() . "\n";
                throw $e;
            }
        );
    }

    /**
     * Generating a random price around the base price
     */
    private function generateRandomPrice(float $basePrice, float $deviation): string
    {
        $randomFactor = 1 + (mt_rand(-100, 100) / 100) * $deviation;
        return number_format($basePrice * $randomFactor, 12, '.', '');
    }

    /**
     * Generating a random volume
     */
    private function generateRandomAmount(float $minAmount, float $maxAmount): string
    {
        return number_format($minAmount + (mt_rand() / mt_getrandmax()) * ($maxAmount - $minAmount), 8, '.', '');
    }

    /**
     * Asynchronously create a group of orders
     */
    public function createBulkOrders(
        string $pair,
        int $numOrders,
        float $basePrice,
        float $priceDeviation,
        float $minAmount,
        float $maxAmount
    ): void {
        echo "🔄 Starting creation of {$numOrders} orders for pair {$pair}...\n";
        
        $promises = [];
        
        for ($i = 0; $i < $numOrders; $i++) {
            $side = mt_rand(0, 1) === 0 ? 1 : 2; // Randomly choose side (1 - sell, 2 - buy)
            $price = $this->generateRandomPrice($basePrice, $priceDeviation);
            $amount = $this->generateRandomAmount($minAmount, $maxAmount);
            
            $promises[] = $this->createLimitOrder($pair, $side, $amount, $price);
        }
        
        \React\Promise\all($promises)->then(
            function () use ($numOrders) {
                echo "✨ Successfully created {$numOrders} orders\n";
                Loop::stop();
            },
            function (\Exception $e) {
                echo "❌ Error during order creation: " . $e->getMessage() . "\n";
                Loop::stop();
            }
        );

        Loop::run();
    }
}

// Usage
if (count($argv) < 7) {
    echo "Usage: php AsyncOrderCreation.php PAIR NUM_ORDERS BASE_PRICE PRICE_DEVIATION MIN_AMOUNT MAX_AMOUNT\n";
    echo "Example: php AsyncOrderCreation.php LTC_USDT 10 65.5 0.02 0.1 0.5\n";
    exit(1);
}

$pair = $argv[1];
$numOrders = (int)$argv[2];
$basePrice = (float)$argv[3];
$priceDeviation = (float)$argv[4];
$minAmount = (float)$argv[5];
$maxAmount = (float)$argv[6];

$creation = new AsyncOrderCreation();
$creation->createBulkOrders($pair, $numOrders, $basePrice, $priceDeviation, $minAmount, $maxAmount); 