#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use React\EventLoop\Loop;
use React\Http\Browser;
use React\Promise\Promise;
use Psr\Http\Message\ResponseInterface;

class AsyncOrderCancellation
{
    private string $tradeServerUrl;
    private Browser $browser;

    public function __construct()
    {
        $this->tradeServerUrl = Config::getTradeServerUrl();
        $this->browser = new Browser();
    }

    /**
     * Asynchronously cancel an order
     */
    private function cancelOrder(int $orderId, string $pair): Promise
    {
        $body = [
            'method' => 'order.cancel',
            'params' => [Config::BOT_USER_ID, $pair, $orderId],
            'id' => 1,
        ];

        return $this->browser->post($this->tradeServerUrl, [
            'Content-Type' => 'application/json'
        ], json_encode($body))
        ->then(
            function (ResponseInterface $response) use ($orderId) {
                $result = json_decode((string) $response->getBody(), true);
                echo "✅ Order {$orderId} successfully cancelled\n";
                return $result;
            },
            function (\Exception $e) use ($orderId) {
                echo "❌ Error cancelling order {$orderId}: " . $e->getMessage() . "\n";
                throw $e;
            }
        );
    }

    /**
     * Getting the list of open orders
     */
    private function getOpenOrders(string $pair): Promise
    {
        $body = [
            'method' => 'order.pending',
            'params' => [Config::BOT_USER_ID, $pair, 0, 100],
            'id' => 1,
        ];

        return $this->browser->post($this->tradeServerUrl, [
            'Content-Type' => 'application/json'
        ], json_encode($body))
        ->then(
            function (ResponseInterface $response) {
                $result = json_decode((string) $response->getBody(), true);
                if (isset($result['result']['records'])) {
                    return $result['result']['records'];
                }
                throw new \Exception('Failed to get the list of orders');
            }
        );
    }

    /**
     * Asynchronously cancel all orders for a pair
     */
    public function cancelAllOrders(string $pair): void
    {
        echo "🔄 Fetching open orders list for pair {$pair}...\n";

        $this->getOpenOrders($pair)
            ->then(function ($orders) use ($pair) {
                echo "📋 Found " . count($orders) . " open orders\n";
                
                // Create an array of promises for each order
                $promises = array_map(
                    fn($order) => $this->cancelOrder($order['id'], $pair),
                    $orders
                );
                
                // Wait for all cancellations to complete
                \React\Promise\all($promises)->then(
                    function () {
                        echo "✨ All orders successfully cancelled\n";
                        Loop::stop();
                    },
                    function (\Exception $e) {
                        echo "❌ Error during order cancellation: " . $e->getMessage() . "\n";
                        Loop::stop();
                    }
                );
            });

        Loop::run();
    }
}

// Usage
if (count($argv) < 2) {
    echo "Usage: php AsyncOrderCancellation.php PAIR\n";
    echo "Example: php AsyncOrderCancellation.php LTC_USDT\n";
    exit(1);
}

$pair = $argv[1];
$cancellation = new AsyncOrderCancellation();
$cancellation->cancelAllOrders($pair); 