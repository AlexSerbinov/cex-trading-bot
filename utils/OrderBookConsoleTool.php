<?php
require './vendor/autoload.php'; // Include Composer autoload for dependencies

use GuzzleHttp\Client;
use Symfony\Component\Console\Output\ConsoleOutput;

// Configuration
// const TRADE_SERVER_URL = 'http://195.7.7.93:18080'; // 93 dev
const TRADE_SERVER_URL = 'http://164.68.117.90:18080'; // 90 demo

const REFRESH_INTERVAL = 500; // Refresh interval in milliseconds

// Get trading pair from command line argument (e.g., php orderbook.php -LTC_USDT)
$pair = isset($argv[1]) && str_starts_with($argv[1], '-') ? substr($argv[1], 1) : 'LTC_USDT';

// ANSI color codes for console output
const COLOR_RED = "\033[31m";
const COLOR_GREEN = "\033[32m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BOLD = "\033[1m";
const COLOR_RESET = "\033[0m";

/**
 * Fetch order book data (bids or asks) from the trade server
 * @param int $side 1 for asks, 2 for bids
 * @return array
 */
function getOrderBook(int $side): array {
    $client = new Client();
    $body = [
        'method' => 'order.book',
        'params' => [$GLOBALS['pair'], $side, 0, 100], // Pair, side, offset, limit
        'id' => 1,
    ];

    try {
        $response = $client->post(TRADE_SERVER_URL, ['json' => $body]);
        $data = json_decode($response->getBody()->getContents(), true);

        if (isset($data['result']['orders'])) {
            return array_map(function ($order) {
                return [
                    'price' => (float)$order['price'],
                    'amount' => (float)$order['amount'],
                    'side' => $order['side'],
                ];
            }, $data['result']['orders']);
        }
        return [];
    } catch (Exception $e) {
        echo "Error fetching order book for side=$side: " . $e->getMessage() . PHP_EOL;
        return [];
    }
}

/**
 * Format the order book for console display
 * @param array $bids Bids data
 * @param array $asks Asks data
 * @return string
 */
function formatOrderBook(array $bids, array $asks): string {
    $maxRows = 15; // Maximum rows to display

    // Sort bids (descending) and asks (ascending) by price
    usort($bids, fn($a, $b) => $b['price'] <=> $a['price']);
    usort($asks, fn($a, $b) => $a['price'] <=> $b['price']);

    // Take only the top $maxRows entries
    $displayBids = array_slice($bids, 0, $maxRows);
    $displayAsks = array_slice($asks, 0, $maxRows);

    // Build output string
    $output = COLOR_BOLD . "Order Book ({$GLOBALS['pair']})" . COLOR_RESET . PHP_EOL;
    $output .= COLOR_BOLD . "№\tPrice (USDT)\tAmount (LTC)\tTotal" . COLOR_RESET . PHP_EOL;

    // Display asks (red) with numbering
    foreach ($displayAsks as $index => $ask) {
        $total = number_format($ask['price'] * $ask['amount'], 2);
        $output .= COLOR_RED . sprintf(
            "%2d\t%.5f\t%.8f\t%s",
            $index + 1,
            $ask['price'],
            $ask['amount'],
            $total
        ) . COLOR_RESET . PHP_EOL;
    }

    // Add last price (yellow)
    $lastPrice = !empty($asks) ? $asks[0]['price'] : (!empty($bids) ? $bids[0]['price'] : 119.13);
    $output .= COLOR_BOLD . COLOR_YELLOW . sprintf("%.2f $ %.2f", $lastPrice, $lastPrice) . COLOR_RESET . PHP_EOL;

    // Display bids (green) with numbering
    foreach ($displayBids as $index => $bid) {
        $total = number_format($bid['price'] * $bid['amount'], 2);
        $output .= COLOR_GREEN . sprintf(
            "%2d\t%.5f\t%.8f\t%s",
            $index + 1,
            $bid['price'],
            $bid['amount'],
            $total
        ) . COLOR_RESET . PHP_EOL;
    }

    return $output;
}

/**
 * Clear console and rewrite the output
 * @param string $output
 */
function clearAndRewrite(string $output): void {
    // Clear the screen and move cursor to top-left
    echo "\033[2J\033[H";
    echo $output;
}

/**
 * Main loop to continuously display the order book
 */
function runOrderBookDisplay(): void {
    while (true) {
        // Fetch bids and asks
        $bids = getOrderBook(2); // Bids
        $asks = getOrderBook(1); // Asks

        $orderBookOutput = formatOrderBook($bids, $asks);
        clearAndRewrite($orderBookOutput);

        usleep(REFRESH_INTERVAL * 1000); // Sleep for REFRESH_INTERVAL milliseconds
    }
}

// Run the order book display
try {
    runOrderBookDisplay();
} catch (Exception $e) {
    echo "Error running order book display: " . $e->getMessage() . PHP_EOL;
}