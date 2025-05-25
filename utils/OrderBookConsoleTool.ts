import axios from 'axios';
import chalk from 'chalk';

// Configuration
const TRADE_SERVER_URL = 'http://195.7.7.93:18080'; // 93 demo
// const TRADE_SERVER_URL = 'http://164.68.117.90:18080'; // 90 dev
const REFRESH_INTERVAL = 500; // Refresh every 500 ms

// Get trading pair from command line arguments
const PAIR = process.argv[2]?.startsWith('-') ? process.argv[2].substring(1) : 'BTC_USDC';

// Function to get order book (bids or asks)
async function getOrderBook(side: number) {
    const body = {
        method: 'order.book',
        params: [PAIR, side, 0, 100], // side: 1 - asks, 2 - bids
        id: 1,
    };
    try {
        const response = await axios.post(TRADE_SERVER_URL, body);
        if (response.data && response.data.result && response.data.result.orders) {
            return response.data.result.orders.map((order: any) => (
                {
                price: parseFloat(order.price),
                amount: parseFloat(order.amount),
                side: order.side,
            }));
        }
        return [];
    } catch (error) {
        console.error(`Error fetching order book for side=${side}:`, error);
        return [];
    }
}

// Function to format order book output
function formatOrderBook(bids: any[], asks: any[]) {
    const maxRows = 15; // Maximum rows to display

    // Sort bids and asks by price (bids - descending, asks - ascending)
    bids.sort((a, b) => b.price - a.price);
    asks.sort((a, b) => a.price - b.price);

    // Take only the first maxRows lines
    const displayBids = bids.slice(0, maxRows);
    const displayAsks = asks.slice(0, maxRows);

    // Format lines for output
    let output = chalk.bold(`Order Book (${PAIR})\n`);
    output += chalk.bold('№\tPrice (USDT)\t\tAmount (SOL)\tTotal\n');

    // Display asks (red) with numbering
    displayAsks.forEach((ask, index) => {
        const total = (ask.price * ask.amount).toFixed(2);
        output += chalk.red(`${index + 1}\t${ask.price.toFixed(12)}\t${ask.amount.toFixed(8)}\t${total}\n`);
        // output += chalk.red(`${index + 1}\t${ask.price}\t${ask.amount.toFixed(8)}\t${total}\n`);
    });

    // Add current price
    const lastPrice = asks.length > 0 ? asks[0].price : bids.length > 0 ? bids[0].price : 119.13;
    output += chalk.bold.yellow(`${lastPrice.toFixed(2)} $ ${lastPrice.toFixed(2)}\n`);

    // Display bids (green) with numbering
    displayBids.forEach((bid, index) => {
        const total = (bid.price * bid.amount).toFixed(2);
        output += chalk.green(`${index + 1}\t${bid.price.toFixed(12)}\t${bid.amount.toFixed(8)}\t${total}\n`);
    });

    return output;
}

// Function to clear console and rewrite lines
function clearAndRewrite(output: string) {
    process.stdout.write('\x1Bc'); // Clear console
    process.stdout.write(output); // Display new order book
}

// Main function
async function runOrderBookDisplay() {
    try {
        while (true) {
            const [bids, asks] = await Promise.all([
                getOrderBook(2), // Bids
                getOrderBook(1), // Asks
            ]);

            const orderBookOutput = formatOrderBook(bids, asks);
            clearAndRewrite(orderBookOutput);

            await new Promise((resolve) => setTimeout(resolve, REFRESH_INTERVAL));
        }
    } catch (error) {
        console.error('Error in order book display operation:', error);
    }
}

// Start
runOrderBookDisplay();
