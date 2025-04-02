<?php

namespace DeadWatcher;

use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Client;
use Dotenv\Dotenv;

require_once __DIR__ . '/../../src/core/Logger.php';
require_once __DIR__ . '/../../src/core/ApiClient.php';
require_once __DIR__ . '/DeadWatcherConfig.php';

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

class DeadWatcher
{
    private $logger;
    private $loop;
    private $pairs = []; // Динамічний список пар: [pair => lastHeartbeatTimestamp]
    private $config;
    private $apiClient;

    public function __construct()
    {
        $this->logger = \Logger::getInstance();
        $this->loop = Loop::get();
        $this->config = new DeadWatcherConfig();
        $this->apiClient = new \ApiClient();
        $this->logger->log("Dead Watcher initialized on port " . $this->config->getPort());
    }

    public function run()
    {
        $server = new HttpServer($this->loop, function (ServerRequestInterface $request) {
            return $this->handleRequest($request);
        });

        $socket = new \React\Socket\SocketServer('0.0.0.0:' . $this->config->getPort(), [], $this->loop);
        $server->listen($socket);

        // Періодична перевірка стану пар
        $this->loop->addPeriodicTimer(1, function () {
            $this->checkHeartbeats();
        });

        $this->logger->log("Dead Watcher server started on port " . $this->config->getPort());
        $this->loop->run();
    }

    private function handleRequest(ServerRequestInterface $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $this->logger->log("Request received: {$method} {$path}");

        if ($method === 'POST' && $path === '/dead-watcher/heartbeat') {
            $body = json_decode((string)$request->getBody(), true);
            if ($body && isset($body['pair']) && isset($body['bot_id']) && isset($body['timestamp'])) {
                $pair = $body['pair'];
                $botId = $body['bot_id'];
                $timestamp = $body['timestamp'];

                if ($botId === 5) { // Перевіряємо тільки для bot_id = 5
                    $this->pairs[$pair] = $timestamp;
                    $this->logger->log("Heartbeat received for pair {$pair} (bot_id 5) at {$timestamp}");
                    return new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => 'ok']));
                } else {
                    $this->logger->log("Heartbeat ignored for pair {$pair}, bot_id {$botId}");
                    // Можливо, варто повернути 200 OK, щоб клієнт не вважав це помилкою
                    return new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => 'ignored']));
                }
            } else {
                $this->logger->log("Invalid heartbeat request body: " . (string)$request->getBody());
                return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'Invalid request body']));
            }
        }
        $this->logger->log("Request not found: {$method} {$path}");
        return new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'Not found']));
    }

    private function checkHeartbeats()
    {
        $currentTime = time();
        $timeout = $this->config->getHeartbeatTimeout();
        // $this->logger->log("Checking heartbeats... Current time: {$currentTime}"); // Може бути занадто багато логів

        foreach ($this->pairs as $pair => $lastHeartbeat) {
            if ($currentTime - $lastHeartbeat > $timeout) {
                $this->logger->log("No heartbeat for {$pair} for over {$timeout} seconds, cancelling orders");
                $this->cancelOrdersForPair($pair);
                unset($this->pairs[$pair]); // Видаляємо пару після скасування ордерів
            }
        }
    }

    private function cancelOrdersForPair(string $pair)
    {
        $this->logger->log("Attempting to cancel all orders for pair {$pair} and bot_id 5");
        try {
            $openOrders = $this->getOpenOrders($pair);
            $orderCount = count($openOrders);
            $this->logger->log("Found {$orderCount} open orders for pair {$pair}");

            if ($orderCount === 0) {
                $this->logger->log("No open orders to cancel for pair {$pair}.");
                return; // Виходимо, якщо немає ордерів для скасування
            }

            foreach ($openOrders as $order) {
                $orderId = $order['id'] ?? 'unknown_id'; // Обробка випадку, якщо id не існує
                $this->logger->log("Attempting to cancel order {$orderId} for pair {$pair}");
                $response = $this->apiClient->post(
                    $this->config->getTradeServerUrl(),
                    json_encode([
                        'method' => 'order.cancel',
                        'params' => [5, $pair, $orderId],
                        'id' => rand(1, 1000)
                    ])
                );
                // Додатково можна логувати відповідь від сервера скасування
                 $this->logger->log("Cancellation response for order {$orderId}: {$response}");
                $this->logger->log("Successfully sent cancellation request for order {$orderId} for pair {$pair}");
            }
            $this->logger->log("Finished cancellation process for pair {$pair}");
        } catch (\Exception $e) {
            $this->logger->error("Error cancelling orders for {$pair}: " . $e->getMessage());
        }
    }

    private function getOpenOrders(string $pair): array
    {
        $this->logger->log("Requesting open orders for pair {$pair} (bot_id 5)");
        try {
            $response = $this->apiClient->post(
                $this->config->getTradeServerUrl(),
                json_encode([
                    'method' => 'order.pending',
                    'params' => [5, $pair, 0, 100], // Запитуємо для bot_id 5
                    'id' => rand(1, 1000)
                ])
            );
            $this->logger->log("Received response for open orders request for pair {$pair}: " . $response);
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error("Error decoding JSON response for open orders {$pair}: " . json_last_error_msg());
                return [];
            }

            if (!isset($data['result']['records'])) {
                 $this->logger->log("No 'result.records' found in open orders response for pair {$pair}. Response: " . $response);
                 return [];
            }

            return $data['result']['records'];
        } catch (\Exception $e) {
            $this->logger->error("Error getting open orders for pair {$pair}: " . $e->getMessage());
            return [];
        }
    }
}

// Запуск Dead Watcher
$deadWatcher = new DeadWatcher();
$deadWatcher->run();