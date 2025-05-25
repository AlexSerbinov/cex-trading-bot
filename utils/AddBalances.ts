import axios from 'axios';

const TRADE_SERVER_URL = 'http://164.68.117.90:18080'; // 90 dev
// const TRADE_SERVER_URL = 'http://195.7.7.93:18080'; // 93 demo
const USER_ID = 5; // User ID
const TARGET_USD_VALUE = 1000000; // Target amount in USD (1 million)

interface Balance {
    available: string;
    freeze: string;
}

interface Balances {
    [key: string]: Balance;
}

interface MarketPair {
    pair: string;
}

async function fetchMarketPairs(): Promise<string[]> {
    try {
        const response = await axios.post(TRADE_SERVER_URL, {
            method: "market.list",
            params: [],
            id: 1
        });

        if (!response.data || !response.data.result) {
            throw new Error('Invalid response format from market.list');
        }

        return Object.keys(response.data.result);
    } catch (error) {
        console.error('Error fetching market pairs:', error);
        return [];
    }
}

async function getUserBalances(userId: number): Promise<Balances> {
    try {
        const response = await axios.post(TRADE_SERVER_URL, {
            method: "balance.query",
            params: [userId],
            id: 1
        });

        if (!response.data || !response.data.result) {
            throw new Error('Invalid response format from balance.query');
        }

        return response.data.result;
    } catch (error) {
        console.error('Error fetching user balances:', error);
        return {};
    }
}

async function updateBalance(userId: number, currency: string, amount: string): Promise<void> {
    try {
        const operationId = Date.now(); // Unique operation ID
        await axios.post(TRADE_SERVER_URL, {
            method: "balance.update",
            params: [userId, currency, "deposit", operationId, amount, {}],
            id: 1
        });
        console.log(`Balance updated for ${currency}: +${amount}`);
    } catch (error) {
        console.error(`Error updating balance for ${currency}:`, error);
        throw error; // Re-throw to handle in the main function
    }
}

async function getCurrencyRateToUSD(currency: string): Promise<number> {
    const rates: { [key: string]: number } = {
        "BTC": 65000,
        "ETH": 3500,
        "USDT": 1,
        "USDC": 1,
        "BNB": 450,
        "SOL": 120,
        "XRP": 0.6,
        "LTC": 80,
        "TON": 3,
        "TRX": 0.1,
        "DOGE": 0.08
    };

    const rate = rates[currency];
    if (!rate) {
        console.warn(`Warning: No rate found for ${currency}, using 1 USD as default`);
        return 1;
    }
    return rate;
}

async function main() {
    try {
        console.log("Fetching market pairs...");
        const marketPairs = await fetchMarketPairs();
        if (marketPairs.length === 0) {
            throw new Error('No market pairs available');
        }
        console.log("Available market pairs:", marketPairs);

        console.log("\nFetching user balances...");
        const balances = await getUserBalances(USER_ID);
        if (Object.keys(balances).length === 0) {
            throw new Error('No balances found for user');
        }
        console.log("Current user balances:", balances);

        const updatePromises: Promise<void>[] = [];

        // For each currency, add 1M USD worth
        for (const [currency, _] of Object.entries(balances)) {
            const rate = await getCurrencyRateToUSD(currency);
            const amountToAdd = (TARGET_USD_VALUE / rate).toFixed(8);
            console.log(`Adding ${amountToAdd} ${currency} (= $${TARGET_USD_VALUE.toFixed(2)})`);
            updatePromises.push(updateBalance(USER_ID, currency, amountToAdd));
        }

        if (updatePromises.length > 0) {
            console.log("\nUpdating balances...");
            await Promise.all(updatePromises);
            console.log("All balances updated successfully");

            // Verify final balances
            const finalBalances = await getUserBalances(USER_ID);
            console.log("\nFinal user balances:", finalBalances);

            // Calculate and show final total value
            let finalTotalUSD = 0;
            for (const [currency, balance] of Object.entries(finalBalances)) {
                const rate = await getCurrencyRateToUSD(currency);
                const totalAmount = parseFloat(balance.available) + parseFloat(balance.freeze);
                finalTotalUSD += totalAmount * rate;
            }
            console.log(`\nFinal total value in USD: $${finalTotalUSD.toFixed(2)}`);
            console.log(`Trade server URL: ${TRADE_SERVER_URL}`);
        } else {
            console.log("\nNo balance updates needed");
        }

    } catch (error) {
        console.error("Error in main function:", error);
        process.exit(1);
    }
}

main();