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
     * Асинхронне скасування ордера
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
                echo "✅ Ордер {$orderId} успішно скасовано\n";
                return $result;
            },
            function (\Exception $e) use ($orderId) {
                echo "❌ Помилка скасування ордера {$orderId}: " . $e->getMessage() . "\n";
                throw $e;
            }
        );
    }

    /**
     * Отримання списку відкритих ордерів
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
                throw new \Exception('Не вдалося отримати список ордерів');
            }
        );
    }

    /**
     * Асинхронне скасування всіх ордерів для пари
     */
    public function cancelAllOrders(string $pair): void
    {
        echo "🔄 Отримання списку відкритих ордерів для пари {$pair}...\n";

        $this->getOpenOrders($pair)
            ->then(function ($orders) use ($pair) {
                echo "📋 Знайдено " . count($orders) . " відкритих ордерів\n";
                
                // Створюємо масив промісів для кожного ордера
                $promises = array_map(
                    fn($order) => $this->cancelOrder($order['id'], $pair),
                    $orders
                );
                
                // Чекаємо завершення всіх скасувань
                \React\Promise\all($promises)->then(
                    function () {
                        echo "✨ Всі ордери успішно скасовано\n";
                        Loop::stop();
                    },
                    function (\Exception $e) {
                        echo "❌ Помилка під час скасування ордерів: " . $e->getMessage() . "\n";
                        Loop::stop();
                    }
                );
            });

        Loop::run();
    }
}

// Використання
if (count($argv) < 2) {
    echo "Використання: php AsyncOrderCancellation.php PAIR\n";
    echo "Приклад: php AsyncOrderCancellation.php LTC_USDT\n";
    exit(1);
}

$pair = $argv[1];
$cancellation = new AsyncOrderCancellation();
$cancellation->cancelAllOrders($pair); 