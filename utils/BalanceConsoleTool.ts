import axios from 'axios';
import chalk from 'chalk';

const TRADE_SERVER_URL = 'http://195.7.7.93:18080'; // 93 demo
// const TRADE_SERVER_URL = 'http://164.68.117.90:18080'; // 90 dev
const USER_ID = 5;
const REFRESH_INTERVAL = 1000; // 1 секунда

interface Balance {
    available: string;
    freeze: string;
}

interface Balances {
    [key: string]: Balance;
}

async function getBalances(): Promise<Balances> {
    try {
        const response = await axios.post(TRADE_SERVER_URL, {
            method: "balance.query",
            params: [USER_ID],
            id: 1
        });

        if (!response.data || !response.data.result) {
            throw new Error('Invalid response format from balance.query');
        }

        return response.data.result;
    } catch (error) {
        console.error('Error fetching balances:', error);
        return {};
    }
}

function formatNumber(num: number): string {
    if (num >= 1000000) {
        return num.toFixed(2);
    } else if (num >= 1) {
        return num.toFixed(8);
    } else {
        return num.toFixed(10);
    }
}

function formatBalances(balances: Balances): string {
    let output = '\n' + chalk.bold.yellow(`╔══════════════════════ Balances for User ID: ${USER_ID} ══════════════════════╗\n`);
    output += chalk.bold.cyan('║ Currency      Available              Frozen                 Total                ║\n');
    output += chalk.bold.yellow('╠═════════════════════════════════════════════════════════════════════════════════╣\n');

    Object.entries(balances)
        .sort(([a], [b]) => a.localeCompare(b))
        .forEach(([currency, balance]) => {
            const available = parseFloat(balance.available);
            const freeze = parseFloat(balance.freeze);
            const total = available + freeze;

            if (total > 0) {
                const formattedAvailable = formatNumber(available);
                const formattedFreeze = formatNumber(freeze);
                const formattedTotal = formatNumber(total);

                output += chalk.green(
                    `║ ${currency.padEnd(12)} ${formattedAvailable.padEnd(22)} ${formattedFreeze.padEnd(22)} ${formattedTotal.padEnd(20)} ║\n`
                );
            }
        });

    output += chalk.bold.yellow('╚═════════════════════════════════════════════════════════════════════════════════╝');
    return output;
}

function clearAndRewrite(output: string) {
    process.stdout.write('\x1Bc');
    process.stdout.write(output);
}

async function runBalanceDisplay() {
    console.log(chalk.cyan('Starting balance monitor...'));
    try {
        while (true) {
            const balances = await getBalances();
            const output = formatBalances(balances);
            clearAndRewrite(output);
            await new Promise(resolve => setTimeout(resolve, REFRESH_INTERVAL));
        }
    } catch (error) {
        console.error(chalk.red('Error in balance display:', error));
    }
}

// Запуск
runBalanceDisplay(); 